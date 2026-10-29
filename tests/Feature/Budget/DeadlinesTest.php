<?php

declare(strict_types=1);

use App\Enums\ExceptionType;
use App\Enums\IntervalUnit;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Family;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Models\PlannedIncome;
use App\Services\Deadlines;
use App\Support\Deadline;
use App\Support\Schedule;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function (): void {
    registerFamily();
});

/** The id of the Bianchi family. */
function deadlinesFamily(): int
{
    return Family::query()->where('name', 'Bianchi')->sole()->id;
}

/**
 * A monthly planned expense of the Bianchi.
 *
 * @param  array<string, mixed>  $attributes
 */
function deadlinesRetta(array $attributes = []): PlannedExpense
{
    return PlannedExpense::factory()->create([
        'family_id' => deadlinesFamily(),
        'title' => 'Retta danza',
        'amount' => 100,
        'start_date' => '2026-09-05',
        ...$attributes,
    ])->fresh();
}

/**
 * An expense of the Bianchi paying a planned expense.
 *
 * @param  array<string, mixed>  $attributes
 */
function deadlinesPaid(PlannedExpense $planned, string $date, ?string $occurrence, array $attributes = []): Expense
{
    return Expense::factory()->create([
        'family_id' => deadlinesFamily(),
        'planned_expense_id' => $planned->id,
        'date' => $date,
        'occurrence_date' => $occurrence,
        ...$attributes,
    ]);
}

/** A day as an immutable date. */
function deadlinesDay(string $day): CarbonImmutable
{
    return CarbonImmutable::parse($day);
}

it('pays the occurrence named by the expense, not the nearest one', function (): void {
    insideBianchi(function (): void {
        $retta = deadlinesRetta();
        deadlinesPaid($retta, '2026-09-05', '2026-09-05');
        deadlinesPaid($retta, '2026-10-05', '2026-10-05');
        $november = deadlinesPaid($retta, '2026-10-20', '2026-11-05');

        $deadlines = resolve(Deadlines::class)->between([$retta], deadlinesDay('2026-09-01'), deadlinesDay('2026-11-30'));

        expect($deadlines)->toHaveCount(3)
            ->and(array_map(fn (Deadline $d): bool => $d->isPaid(), $deadlines))->toBe([true, true, true])
            ->and($deadlines[2]->occurrence->originalDate->toDateString())->toBe('2026-11-05')
            ->and($deadlines[2]->payment?->is($november))->toBeTrue()
            ->and($deadlines[0]->payment?->date?->toDateString())->toBe('2026-09-05')
            ->and($deadlines[0]->kind())->toBe('expense');
    });
});

it('leaves unpaid the occurrence that no expense names', function (): void {
    insideBianchi(function (): void {
        $retta = deadlinesRetta();
        deadlinesPaid($retta, '2026-10-05', null);

        $deadlines = resolve(Deadlines::class)->between([$retta], deadlinesDay('2026-10-01'), deadlinesDay('2026-10-31'));

        expect($deadlines)->toHaveCount(1)->and($deadlines[0]->isPaid())->toBeFalse();
    });
});

it('pays a one-off planned expense with any linked expense', function (): void {
    insideBianchi(function (): void {
        $once = deadlinesRetta(['is_recurring' => false, 'start_date' => '2026-10-10']);
        deadlinesPaid($once, '2026-12-01', null);

        $deadlines = resolve(Deadlines::class)->between([$once], deadlinesDay('2026-10-01'), deadlinesDay('2026-10-31'));

        expect($deadlines)->toHaveCount(1)->and($deadlines[0]->isPaid())->toBeTrue();
    });
});

it('pays a planned income with the income that names its occurrence', function (): void {
    insideBianchi(function (): void {
        $salary = PlannedIncome::factory()->create(['family_id' => deadlinesFamily(), 'amount' => 1500, 'start_date' => '2026-09-27'])->fresh();
        Income::factory()->create(['family_id' => deadlinesFamily(), 'planned_income_id' => $salary->id, 'date' => '2026-09-28', 'occurrence_date' => '2026-09-27']);

        $deadlines = resolve(Deadlines::class)->between([$salary], deadlinesDay('2026-09-01'), deadlinesDay('2026-10-31'));

        expect($deadlines)->toHaveCount(2)
            ->and($deadlines[0]->isPaid())->toBeTrue()
            ->and($deadlines[1]->isPaid())->toBeFalse()
            ->and($deadlines[0]->kind())->toBe('income');
    });
});

it('is late only after its day', function (): void {
    insideBianchi(function (): void {
        $once = deadlinesRetta(['is_recurring' => false, 'start_date' => '2026-10-26']);
        $deadline = resolve(Deadlines::class)->between([$once], deadlinesDay('2026-10-01'), deadlinesDay('2026-10-31'))[0];

        expect($deadline->isLate(CarbonImmutable::parse('2026-10-26 23:30:00')))->toBeFalse()
            ->and($deadline->isLate(CarbonImmutable::parse('2026-10-27 00:10:00')))->toBeTrue();

        deadlinesPaid($once, '2026-10-26', null);
        $paid = resolve(Deadlines::class)->between([$once], deadlinesDay('2026-10-01'), deadlinesDay('2026-10-31'))[0];

        expect($paid->isLate(CarbonImmutable::parse('2026-11-30')))->toBeFalse();
    });
});

it('takes the title of the document to pay', function (): void {
    insideBianchi(function (): void {
        $luce = deadlinesRetta(['title' => 'Luce', 'start_date' => '2026-08-23']);
        Document::factory()->create([
            'family_id' => deadlinesFamily(),
            'title' => 'Bolletta luce di settembre',
            'planned_expense_id' => $luce->id,
            'date' => '2026-10-20',
        ]);

        $deadlines = resolve(Deadlines::class)->between([$luce], deadlinesDay('2026-09-01'), deadlinesDay('2026-10-31'));

        expect($deadlines)->toHaveCount(2)
            ->and($deadlines[0]->title)->toBe('Luce')
            ->and($deadlines[1]->title)->toBe('Bolletta luce di settembre');
    });
});

it('finds only real occurrences', function (): void {
    insideBianchi(function (): void {
        $retta = deadlinesRetta();

        expect(resolve(Deadlines::class)->find($retta, deadlinesDay('2026-12-05'))->occurrence->originalDate->toDateString())->toBe('2026-12-05');
        resolve(Deadlines::class)->find($retta, deadlinesDay('2026-12-06'));
    });
})->throws(NotFoundHttpException::class);

it('lists the late deadlines and those up to a day, unpaid only', function (): void {
    insideBianchi(function (): void {
        $retta = deadlinesRetta(['start_date' => '2026-09-05']);
        deadlinesPaid($retta, '2026-09-05', '2026-09-05');

        $list = resolve(Deadlines::class)->unpaidUntil([$retta], deadlinesDay('2026-12-31'), deadlinesDay('2026-10-26'));

        expect(array_map(fn (Deadline $d): string => $d->occurrence->originalDate->toDateString(), $list))
            ->toBe(['2026-10-05', '2026-11-05', '2026-12-05']);
    });
});

it('never reads another family', function (): void {
    $rossi = Family::factory()->create();
    $foreign = PlannedExpense::factory()->create(['family_id' => $rossi->id, 'start_date' => '2026-09-05'])->fresh();
    Expense::factory()->create(['family_id' => $rossi->id, 'planned_expense_id' => $foreign->id, 'date' => '2026-09-05', 'occurrence_date' => '2026-09-05']);

    insideBianchi(function () use ($foreign): void {
        $deadlines = resolve(Deadlines::class);

        expect($deadlines->between([$foreign], deadlinesDay('2026-09-01'), deadlinesDay('2026-12-31')))->toBe([])
            ->and($deadlines->unpaidUntil([$foreign], deadlinesDay('2026-12-31'), deadlinesDay('2026-10-26')))->toBe([]);
    });
});

it('reads the schedule from the real models', function (): void {
    insideBianchi(function (): void {
        $retta = deadlinesRetta(['interval' => 0, 'amount' => 80]);
        PlannedExpenseException::factory()->create(['family_id' => deadlinesFamily(), 'planned_expense_id' => $retta->id, 'type' => ExceptionType::Skip, 'occurrence_date' => null]);
        PlannedExpenseException::factory()->create([
            'family_id' => deadlinesFamily(), 'planned_expense_id' => $retta->id, 'type' => ExceptionType::AmountOverride,
            'occurrence_date' => '2026-10-05', 'amount_override' => '12.50',
        ]);

        $schedule = Schedule::of($retta->load('exceptions'));

        expect($schedule->interval)->toBe(1)
            ->and($schedule->unit)->toBe(IntervalUnit::Month)
            ->and($schedule->amount)->toBe(80.0)
            ->and($schedule->exceptions)->toHaveCount(1)
            ->and($schedule->exceptions[0]->amount)->toBe(12.5);
    });
});

it('reads the schedule of a planned item just made by a factory', function (): void {
    insideBianchi(function (): void {
        $expense = PlannedExpense::factory()->create(['family_id' => deadlinesFamily(), 'amount' => 30, 'start_date' => '2026-10-01']);
        $income = PlannedIncome::factory()->create(['family_id' => deadlinesFamily(), 'amount' => 900, 'start_date' => '2026-10-27']);

        $expenseSchedule = Schedule::of($expense);
        $incomeSchedule = Schedule::of($income);

        expect($expenseSchedule->estimated)->toBeFalse()
            ->and($expenseSchedule->recurring)->toBeTrue()
            ->and($expenseSchedule->interval)->toBe(1)
            ->and($incomeSchedule->estimated)->toBeFalse()
            ->and($incomeSchedule->unit)->toBe(IntervalUnit::Month);
    });
});
