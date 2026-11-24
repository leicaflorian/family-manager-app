<?php

declare(strict_types=1);

use App\Actions\Families\ChangeFeatures;
use App\Data\HomeData;
use App\Enums\ExceptionType;
use App\Enums\LoanStatus;
use App\Models\Expense;
use App\Models\Family;
use App\Models\Loan;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Services\CardBudgets;
use App\Services\Deadlines;
use App\Services\FamilyFeatures;
use App\Services\HomeSummary;
use App\Support\Deadline;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-10 10:00', 'Europe/Rome'));
    $this->marco = registerFamily();
    $this->family = Family::query()->whereKey($this->marco->family_id)->sole();
    // The fee of the gym from 15/05/2026, paid from May to July, and the salary from 27/08/2026, never recorded
    $this->expense = PlannedExpense::factory()->create(['family_id' => $this->family->id, 'title' => 'Palestra', 'amount' => 40, 'start_date' => '2026-05-15'])->fresh();
    $this->income = PlannedIncome::factory()->create(['family_id' => $this->family->id, 'amount' => 1500, 'start_date' => '2026-08-27'])->fresh();
    $this->paid = array_map(fn (string $day): Expense => Expense::factory()->create([
        'family_id' => $this->family->id, 'planned_expense_id' => $this->expense->id, 'amount' => 40, 'date' => $day, 'occurrence_date' => $day,
    ]), ['2026-05-15', '2026-06-15', '2026-07-15']);
});

afterEach(fn () => $this->travelBack());

/**
 * Switches the Budget of the family of the test through the settings action.
 *
 * @param  object  $test  the test, with its `family`
 * @param  bool  $on  whether the Budget is turned on
 */
function budgetBackOnSwitch(object $test, bool $on): void
{
    resolve(ChangeFeatures::class)->handle($test->family->fresh(), budget: $on, mail: false);
}

/**
 * Turns the Budget off on 10/08/2026 and on again on 20/11/2026, the day the test then looks from.
 *
 * @param  object  $test  the test, with its `family`
 */
function budgetBackOnPause(object $test): void
{
    budgetBackOnSwitch($test, false);
    $test->travelTo(CarbonImmutable::parse('2026-11-20 09:00', 'Europe/Rome'));
    budgetBackOnSwitch($test, true);
}

/**
 * The open due dates of planned items up to a day, as `kind date`, read inside the family of an account.
 *
 * @param  User  $user  the account
 * @param  list<PlannedExpense|PlannedIncome>  $planned  the planned items
 * @param  string  $until  the last day
 * @return list<string>
 */
function budgetBackOnUnpaid(User $user, array $planned, string $until): array
{
    $today = CarbonImmutable::today('Europe/Rome');

    return insideFamilyOf($user, fn (): array => array_map(
        static fn (Deadline $deadline): string => $deadline->kind().' '.$deadline->occurrence->date->toDateString().($deadline->isLate($today) ? ' late' : ''),
        resolve(Deadlines::class)->unpaidUntil($planned, CarbonImmutable::parse($until, 'Europe/Rome'), $today),
    ));
}

/**
 * The Home of the account of the test, today.
 *
 * @param  object  $test  the test, with its `marco`
 */
function budgetBackOnHome(object $test): HomeData
{
    return insideFamilyOf($test->marco, fn (): HomeData => resolve(HomeSummary::class)->for($test->marco, CarbonImmutable::today('Europe/Rome')));
}

/** The open due dates on 20/11/2026 of a family that never paused its Budget. */
const BUDGET_BACK_ON_NO_PAUSE = [
    'expense 2026-08-15 late', 'income 2026-08-27 late', 'expense 2026-09-15 late', 'income 2026-09-27 late',
    'expense 2026-10-15 late', 'income 2026-10-27 late', 'expense 2026-11-15 late', 'income 2026-11-27', 'expense 2026-12-15', 'income 2026-12-27',
];

test('turning the Budget off writes the first pause, turning it on again the day it starts again', function (): void {
    expect($this->family->fresh())->budget_paused_on->toBeNull()->budget_started_on->toBeNull();

    budgetBackOnPause($this);

    expect($this->family->fresh()->budget_paused_on?->toDateString())->toBe('2026-08-10')
        ->and($this->family->fresh()->budget_started_on?->toDateString())->toBe('2026-11-20');

    $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00', 'Europe/Rome'));
    budgetBackOnSwitch($this, false);

    expect($this->family->fresh()->budget_paused_on?->toDateString())->toBe('2026-08-10')
        ->and($this->family->fresh()->budget_started_on?->toDateString())->toBe('2026-11-20');
});

test('a new family turning the Budget on for the first time waives nothing', function (): void {
    $this->family->forceFill(['budget_enabled' => false])->save();
    resolve(FamilyFeatures::class)->forget($this->family);
    $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00', 'Europe/Rome'));

    budgetBackOnSwitch($this, true);

    expect($this->family->fresh())->budget_paused_on->toBeNull()->budget_started_on->toBeNull()
        ->and(budgetBackOnUnpaid($this->marco, [$this->expense, $this->income], '2026-12-31'))->toBe(BUDGET_BACK_ON_NO_PAUSE);
});

test('without a pause the past due dates are late as before', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00', 'Europe/Rome'));

    expect(budgetBackOnUnpaid($this->marco, [$this->expense, $this->income], '2026-12-31'))->toBe(BUDGET_BACK_ON_NO_PAUSE);
});

test('the due dates of the months the Budget was off are neither late nor to pay', function (): void {
    budgetBackOnPause($this);

    expect(budgetBackOnUnpaid($this->marco, [$this->expense, $this->income], '2026-12-31'))->toBe([
        'income 2026-11-27', 'expense 2026-12-15', 'income 2026-12-27',
    ])->and(budgetBackOnHome($this)->late)->toBe(0);
});

test('a due date left unpaid before the pause stays late after the restart', function (): void {
    $this->paid[2]->delete();

    budgetBackOnPause($this);

    expect(budgetBackOnUnpaid($this->marco, [$this->expense, $this->income], '2026-12-31'))->toBe([
        'expense 2026-07-15 late', 'income 2026-11-27', 'expense 2026-12-15', 'income 2026-12-27',
    ]);
});

test('a due date after the Budget came back that passes unpaid is late', function (): void {
    budgetBackOnPause($this);
    $this->travelTo(CarbonImmutable::parse('2026-12-20 09:00', 'Europe/Rome'));

    expect(budgetBackOnUnpaid($this->marco, [$this->expense, $this->income], '2026-12-31'))->toBe([
        'income 2026-11-27 late', 'expense 2026-12-15 late', 'income 2026-12-27',
    ])->and(budgetBackOnHome($this)->late)->toBe(1);
});

test('the due dates of the pause stay there, waived, and the payments recorded before it keep theirs', function (): void {
    budgetBackOnPause($this);

    $found = insideFamilyOf($this->marco, fn (): array => resolve(Deadlines::class)->between([$this->expense], CarbonImmutable::parse('2026-05-01'), CarbonImmutable::parse('2026-11-30')));
    $today = CarbonImmutable::today('Europe/Rome');

    expect(array_map(static fn (Deadline $deadline): string => $deadline->occurrence->date->toDateString().' '.match (true) {
        $deadline->isPaid() => 'paid',
        $deadline->isWaived() => 'waived',
        $deadline->isLate($today) => 'late',
        default => 'due',
    }, $found))->toBe([
        '2026-05-15 paid', '2026-06-15 paid', '2026-07-15 paid', '2026-08-15 waived', '2026-09-15 waived', '2026-10-15 waived', '2026-11-15 waived',
    ])->and($found[0]->payment?->is($this->paid[0]))->toBeTrue()
        ->and(array_filter($found, static fn (Deadline $deadline): bool => $deadline->isDue()))->toBe([]);
});

test('a payment of a month of the pause can still be recorded', function (): void {
    budgetBackOnPause($this);

    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'expense', 'title' => 'Palestra settembre', 'amount' => 40, 'date' => '2026-09-15', 'planned_id' => $this->expense->id])
        ->assertSessionHasNoErrors();

    $deadline = insideFamilyOf($this->marco, fn (): Deadline => resolve(Deadlines::class)->find($this->expense, CarbonImmutable::parse('2026-09-15', 'Europe/Rome')));

    expect($deadline->isPaid())->toBeTrue()
        ->and($deadline->payment?->title)->toBe('Palestra settembre')
        ->and(budgetBackOnHome($this)->late)->toBe(0);
});

test('a due date moved across the restart counts on its real date', function (): void {
    insideFamilyOf($this->marco, function (): void {
        // November moves after the restart, December back into the pause
        PlannedExpenseException::factory()->create(['family_id' => $this->family->id, 'planned_expense_id' => $this->expense->id, 'type' => ExceptionType::DateOverride, 'occurrence_date' => '2026-11-15', 'date_override' => '2026-11-25']);
        PlannedExpenseException::factory()->create(['family_id' => $this->family->id, 'planned_expense_id' => $this->expense->id, 'type' => ExceptionType::DateOverride, 'occurrence_date' => '2026-12-15', 'date_override' => '2026-11-10']);
    });

    budgetBackOnPause($this);

    expect(budgetBackOnUnpaid($this->marco, [$this->expense], '2027-01-31'))->toBe(['expense 2026-11-25', 'expense 2027-01-15']);
});

test('the instalments of a loan planned before the pause start again from zero, and the loan counts as before', function (): void {
    $this->family->forceFill(['budget_enabled' => false])->save();
    resolve(FamilyFeatures::class)->forget($this->family);
    $loan = insideFamilyOf($this->marco, fn (): Loan => Loan::query()->create([
        'name' => 'Prestito auto',
        'status' => LoanStatus::Active,
        'start_date' => '2025-01-01',
        'installments_count' => 36,
        'installment_amount' => 300,
    ]));
    budgetBackOnSwitch($this, true);
    $plan = insideFamilyOf($this->marco, fn (): PlannedExpense => PlannedExpense::query()->where('card_id', $loan->id)->sole());

    budgetBackOnPause($this);

    $budget = insideFamilyOf($this->marco, fn () => resolve(CardBudgets::class)->of(Loan::query()->findOrFail($loan->id), $this->marco, CarbonImmutable::today('Europe/Rome')));

    expect(budgetBackOnUnpaid($this->marco, [$plan], '2026-12-31'))->toBe(['expense 2026-12-01'])
        // No skipped due date stands in for the pause: the plan stays as it was
        ->and(insideFamilyOf($this->marco, fn (): int => PlannedExpense::query()->where('card_id', $loan->id)->count()))->toBe(1)
        ->and(insideFamilyOf($this->marco, fn (): int => $plan->exceptions()->count()))->toBe(0)
        // 01/12/2026 is the 24th instalment from 01/01/2025, counted from the start of the loan
        ->and($budget?->next?->date)->toBe('2026-12-01')
        ->and($budget?->installment_number)->toBe(24)
        ->and($budget?->installments_count)->toBe(36);
});
