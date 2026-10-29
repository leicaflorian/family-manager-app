<?php

declare(strict_types=1);

use App\Data\DeadlineData;
use App\Data\MovementData;
use App\Enums\Role;
use App\Models\Expense;
use App\Models\Family;
use App\Models\Organization;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Models\Utility;
use App\Services\Deadlines;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    App::setLocale('it');
    $this->seed(BianchiFamilySeeder::class);
});

/** The data of a due date of the Retta danza of the Bianchi. */
function budgetDataRetta(string $day): DeadlineData
{
    return insideBianchi(function () use ($day): DeadlineData {
        $retta = PlannedExpense::query()->where('title', 'Retta danza')->sole();
        $deadline = resolve(Deadlines::class)->find($retta, CarbonImmutable::parse($day, 'Europe/Rome'));

        return DeadlineData::fromDeadline($deadline, CarbonImmutable::today('Europe/Rome'));
    });
}

test('it sends a due date with its payment', function (): void {
    $data = budgetDataRetta('2026-11-05');

    expect($data->kind)->toBe('expense')
        ->and($data->original_date)->toBe('2026-11-05')
        ->and($data->amount)->toBe(45.0)
        ->and($data->late)->toBeFalse()
        ->and($data->payment?->date)->toBe('2026-10-20')
        ->and($data->payment?->amount)->toBe(45.0)
        ->and($data->payment?->account)->toBe('Carta Laura')
        ->and($data->card?->type)->toBe('enrollment')
        ->and($data->person)->toBe('Giulia');
});

test('it sends a changed amount with the old one', function (): void {
    $data = budgetDataRetta('2026-12-05');

    expect($data->amount)->toBe(60.0)
        ->and($data->base_amount)->toBe(45.0)
        ->and($data->notes)->toBe('Comprende la quota del saggio di Natale')
        ->and($data->payment)->toBeNull();
});

test('it lets a member edit a movement but not delete it', function (): void {
    $member = User::factory()->create(['family_id' => Family::query()->where('name', 'Bianchi')->sole()->id, 'role' => Role::Member]);

    $data = insideBianchi(fn (): MovementData => MovementData::fromMovement(Expense::query()->where('title', 'Visita dal dentista')->sole(), $member));

    expect($data->kind)->toBe('expense')
        ->and($data->amount)->toBe(80.0)
        ->and($data->category)->not->toBeNull()
        ->and($data->can_edit)->toBeTrue()
        ->and($data->can_delete)->toBeFalse();
});

/**
 * How many queries it takes to build the data of every due date between two days.
 */
function budgetDataQueries(string $from, string $to): array
{
    return insideBianchi(function () use ($from, $to): array {
        $planned = [...PlannedExpense::query()->get()->all(), ...PlannedIncome::query()->get()->all()];

        DB::flushQueryLog();
        DB::enableQueryLog();

        $today = CarbonImmutable::today('Europe/Rome');
        $deadlines = resolve(Deadlines::class)->between($planned, CarbonImmutable::parse($from, 'Europe/Rome'), CarbonImmutable::parse($to, 'Europe/Rome'));
        foreach ($deadlines as $deadline) {
            DeadlineData::fromDeadline($deadline, $today);
        }

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$count, count($deadlines)];
    });
}

test('it builds the data of the due dates with a fixed number of queries', function (): void {
    [$oneMonth, $oneMonthDeadlines] = budgetDataQueries('2026-10-01', '2026-10-31');
    [$threeMonths, $threeMonthsDeadlines] = budgetDataQueries('2026-08-01', '2026-10-31');

    expect($threeMonthsDeadlines)->toBeGreaterThan($oneMonthDeadlines)
        ->and($threeMonths)->toBe($oneMonth);
});

/**
 * Adds planned expenses, each on its own new utility with its own organization, to the Bianchi.
 */
function budgetDataAddCards(int $count): void
{
    insideBianchi(function () use ($count): void {
        $family = Family::query()->where('name', 'Bianchi')->sole()->id;

        for ($i = 0; $i < $count; $i++) {
            $utility = Utility::factory()->create(['family_id' => $family, 'organization_id' => Organization::factory()->create(['family_id' => $family])->id]);
            PlannedExpense::factory()->create(['family_id' => $family, 'title' => 'Extra '.$i, 'amount' => 10, 'start_date' => '2026-10-10', 'is_recurring' => false, 'utility_id' => $utility->id]);
        }
    });
}

test('it builds the data with the same number of queries however many distinct cards there are', function (): void {
    budgetDataAddCards(1);
    [$few] = budgetDataQueries('2026-10-01', '2026-10-31');
    budgetDataAddCards(4);
    [$many, $deadlines] = budgetDataQueries('2026-10-01', '2026-10-31');

    expect($deadlines)->toBeGreaterThanOrEqual(5)
        ->and($many)->toBe($few);
});

test('it falls back to the default account when the planned item has none', function (): void {
    $data = insideBianchi(function (): DeadlineData {
        $planned = PlannedExpense::query()->where('title', 'Assicurazione auto')->sole();
        $planned->update(['financial_account_id' => null]);
        $deadline = resolve(Deadlines::class)->find($planned->refresh(), CarbonImmutable::parse('2026-11-21', 'Europe/Rome'));

        return DeadlineData::fromDeadline($deadline, CarbonImmutable::today('Europe/Rome'));
    });

    expect($data->account)->toBe('Conto BPER')
        ->and($data->account_id)->toBeInt();
});
