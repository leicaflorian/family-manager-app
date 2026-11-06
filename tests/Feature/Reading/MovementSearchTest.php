<?php

declare(strict_types=1);

use App\Models\Employment;
use App\Models\Expense;
use App\Models\Family;
use App\Models\Income;
use App\Models\Organization;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\Reading\MovementSearch;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->bianchi = Family::factory()->create(['name' => 'Bianchi']);
    $this->rossi = Family::factory()->create(['name' => 'Rossi']);
    CurrentFamily::set($this->bianchi->id);
    $this->search = new MovementSearch;
    $this->day = CarbonImmutable::parse('2026-03-15');
});

afterEach(fn () => CurrentFamily::clear());

/**
 * An expense of the Bianchi.
 *
 * @param  array<string, mixed>  $attributes
 */
function expenseOf(array $attributes = []): Expense
{
    return Expense::factory()->for(test()->bianchi)->create($attributes + ['amount' => 50, 'date' => '2026-03-15']);
}

/**
 * An income of the Bianchi.
 *
 * @param  array<string, mixed>  $attributes
 */
function incomeOf(array $attributes = []): Income
{
    return Income::factory()->for(test()->bianchi)->create($attributes + ['amount' => 50, 'date' => '2026-03-15']);
}

/**
 * A planned expense of the Bianchi.
 *
 * @param  array<string, mixed>  $attributes
 */
function plannedExpenseOf(array $attributes = []): PlannedExpense
{
    return PlannedExpense::factory()->for(test()->bianchi)->create($attributes);
}

/**
 * An organization of the Bianchi.
 */
function organizationOf(): Organization
{
    return Organization::factory()->for(test()->bianchi)->create();
}

describe('search windows', function (): void {
    test('findDuplicate: 20 days around the date', function (): void {
        $planned = plannedExpenseOf();
        $inside = expenseOf(['planned_expense_id' => $planned->id, 'date' => '2026-04-04']);

        expect($this->search->duplicate('expense', $planned, $this->day)?->id)->toBe($inside->id);

        $inside->update(['date' => '2026-04-05']);
        expect($this->search->duplicate('expense', $planned, $this->day))->toBeNull();

        $inside->update(['date' => '2026-02-23']);
        expect($this->search->duplicate('expense', $planned, $this->day)?->id)->toBe($inside->id);

        $inside->update(['date' => '2026-02-22']);
        expect($this->search->duplicate('expense', $planned, $this->day))->toBeNull();
    });

    test('findDuplicate: an income of the same planned income', function (): void {
        $planned = PlannedIncome::factory()->for(test()->bianchi)->create();
        $income = incomeOf(['planned_income_id' => $planned->id, 'date' => '2026-03-20']);
        incomeOf(['date' => '2026-03-15']);

        expect($this->search->duplicate('income', $planned, $this->day)?->id)->toBe($income->id);
    });

    test('findDuplicate: a movement of another planned expense is not a duplicate', function (): void {
        $planned = plannedExpenseOf();
        expenseOf(['planned_expense_id' => plannedExpenseOf()->id]);

        expect($this->search->duplicate('expense', $planned, $this->day))->toBeNull();
    });

    test('findPaid: 30 days around the date', function (): void {
        $row = expenseOf(['date' => '2026-04-14']);

        expect($this->search->paid(50, $this->day, null)['match']?->id)->toBe($row->id);

        $row->update(['date' => '2026-04-15']);
        expect($this->search->paid(50, $this->day, null)['match'])->toBeNull();

        $row->update(['date' => '2026-02-13']);
        expect($this->search->paid(50, $this->day, null)['match']?->id)->toBe($row->id);

        $row->update(['date' => '2026-02-12']);
        expect($this->search->paid(50, $this->day, null)['match'])->toBeNull();
    });

    test('findSimilar: 3 days around the date without the issuer', function (): void {
        $row = incomeOf(['date' => '2026-03-18']);

        expect($this->search->similar('income', 50, $this->day, null)?->id)->toBe($row->id);

        $row->update(['date' => '2026-03-19']);
        expect($this->search->similar('income', 50, $this->day, null))->toBeNull();

        $row->update(['date' => '2026-03-12']);
        expect($this->search->similar('income', 50, $this->day, null)?->id)->toBe($row->id);

        $row->update(['date' => '2026-03-11']);
        expect($this->search->similar('income', 50, $this->day, null))->toBeNull();
    });

    test('findSimilar: 20 days around the date with the issuer, of the same issuer', function (): void {
        $organization = organizationOf();
        $row = incomeOf(['date' => '2026-04-04', 'organization_id' => $organization->id]);

        expect($this->search->similar('income', 50, $this->day, $organization->id)?->id)->toBe($row->id);

        $row->update(['date' => '2026-04-05']);
        expect($this->search->similar('income', 50, $this->day, $organization->id))->toBeNull();

        $row->update(['date' => '2026-03-15', 'organization_id' => organizationOf()->id]);
        expect($this->search->similar('income', 50, $this->day, $organization->id))->toBeNull();
    });
});

describe('findPaid: the rows after the query', function (): void {
    test('the fee is taken from the amount of the expense: 60 with a fee of 10 pays 50, 50 with a fee of 10 does not', function (): void {
        expenseOf(['amount' => 50, 'fee_amount' => 10, 'date' => '2026-03-15']);
        expenseOf(['amount' => 60, 'fee_amount' => 0, 'date' => '2026-03-15']);
        $paid = expenseOf(['amount' => 60, 'fee_amount' => 10, 'date' => '2026-03-10']);

        $result = $this->search->paid(50, $this->day, null);

        expect($result['match']?->id)->toBe($paid->id)
            ->and($result['others'])->toBe(0);
    });

    test('amounts as decimals and a missing fee are compared in cents: 30.10 less 0.10 is 30', function (): void {
        $withFee = expenseOf(['amount' => '30.10', 'fee_amount' => '0.10']);
        $noFee = expenseOf(['amount' => '30.10', 'fee_amount' => null]);

        expect($this->search->paid(30, $this->day, null)['match']?->id)->toBe($withFee->id)
            ->and($this->search->paid(30.1, $this->day, null)['match']?->id)->toBe($noFee->id);
    });

    test('nothing matches: null and zero others', function (): void {
        expenseOf(['amount' => 49.99, 'fee_amount' => 0]);

        expect($this->search->paid(50, $this->day, null))->toBe(['match' => null, 'others' => 0]);
    });

    test('an expense of another issuer is left out, one with no issuer stays, the same issuer comes first', function (): void {
        $four = organizationOf();
        expenseOf(['organization_id' => organizationOf()->id]);
        expenseOf(['organization_id' => null]);
        $same = expenseOf(['organization_id' => $four->id, 'date' => '2026-03-01']);

        $result = $this->search->paid(50, $this->day, $four->id);

        expect($result['match']?->id)->toBe($same->id)
            ->and($result['others'])->toBe(1);
    });

    test('without the issuer on the receipt every issuer stays and the closest date comes first; others counts the rest', function (): void {
        expenseOf(['date' => '2026-03-01', 'organization_id' => organizationOf()->id]);
        $closest = expenseOf(['date' => '2026-03-14', 'organization_id' => organizationOf()->id]);
        expenseOf(['date' => '2026-03-20', 'organization_id' => null]);
        expenseOf(['date' => '2026-03-14', 'amount' => 51]);

        $result = $this->search->paid(50, $this->day, null);

        expect($result['match']?->id)->toBe($closest->id)
            ->and($result['others'])->toBe(2);
    });

    test('the same issuer wins over a closer date', function (): void {
        $four = organizationOf();
        expenseOf(['date' => '2026-03-15', 'organization_id' => null]);
        $same = expenseOf(['date' => '2026-03-05', 'organization_id' => $four->id]);

        expect($this->search->paid(50, $this->day, $four->id)['match']?->id)->toBe($same->id);
    });

    test('same issuer and same distance (before and after): the lower id first', function (): void {
        $four = organizationOf();
        $lower = expenseOf(['date' => '2026-03-16', 'organization_id' => $four->id]);
        expenseOf(['date' => '2026-03-14', 'organization_id' => $four->id]);

        $result = $this->search->paid(50, $this->day, $four->id);

        expect($result['match']?->id)->toBe($lower->id)
            ->and($result['others'])->toBe(1);
    });

    test('an expense of another family is not found', function (): void {
        Expense::factory()->for(test()->rossi)->create(['amount' => 50, 'date' => '2026-03-15']);

        expect($this->search->paid(50, $this->day, null))->toBe(['match' => null, 'others' => 0]);
    });
});

describe('findSimilar on expenses', function (): void {
    test('subtracts the fee from the amount', function (): void {
        expenseOf(['amount' => 40, 'fee_amount' => 10]);
        $row = expenseOf(['amount' => 50, 'fee_amount' => 10]);

        expect($this->search->similar('expense', 40, $this->day, null)?->id)->toBe($row->id);
    });

    test('an expense whose amount is the paper amount only before the fee does not match', function (): void {
        expenseOf(['amount' => 40, 'fee_amount' => 10]);

        expect($this->search->similar('expense', 40, $this->day, null))->toBeNull();
    });

    test('a missing fee counts as zero', function (): void {
        expenseOf(['amount' => 41, 'fee_amount' => null]);
        $row = expenseOf(['amount' => 40, 'fee_amount' => null]);

        expect($this->search->similar('expense', 40, $this->day, null)?->id)->toBe($row->id);
    });

    test('two matching rows: the first one', function (): void {
        $first = expenseOf(['amount' => 40, 'fee_amount' => 0]);
        expenseOf(['amount' => 45, 'fee_amount' => 5]);

        expect($this->search->similar('expense', 40, $this->day, null)?->id)->toBe($first->id);
    });

    test('with the issuer the window is 20 days and the issuer must be the same', function (): void {
        $four = organizationOf();
        $row = expenseOf(['amount' => 40, 'date' => '2026-04-04', 'organization_id' => $four->id]);
        expenseOf(['amount' => 40, 'date' => '2026-03-15', 'organization_id' => organizationOf()->id]);

        expect($this->search->similar('expense', 40, $this->day, $four->id)?->id)->toBe($row->id);

        $row->update(['date' => '2026-04-05']);
        expect($this->search->similar('expense', 40, $this->day, $four->id))->toBeNull();
    });
});

describe('findPlanned', function (): void {
    test('the planned expense of another card is not found', function (): void {
        $utility = Utility::factory()->for(test()->bianchi)->create();
        PlannedExpense::factory()->for(test()->bianchi)->forCard($utility)->create();
        $other = Utility::factory()->for(test()->bianchi)->create();

        expect($this->search->planned('expense', $other, null, $this->day))->toBeNull();
    });

    test('no card: nothing is found', function (): void {
        plannedExpenseOf();

        expect($this->search->planned('expense', null, null, $this->day))->toBeNull();
        expect($this->search->planned('income', null, null, $this->day))->toBeNull();
    });

    test('a single commitment is returned even if its dates do not contain the date', function (): void {
        $utility = Utility::factory()->for(test()->bianchi)->create();
        $row = PlannedExpense::factory()->for(test()->bianchi)->forCard($utility)->create(['start_date' => '2025-01-01', 'end_date' => '2025-12-31']);

        expect($this->search->planned('expense', $utility, null, $this->day)?->id)->toBe($row->id);
    });

    test('several and no date on the paper: the newest start', function (): void {
        $utility = Utility::factory()->for(test()->bianchi)->create();
        $newest = PlannedExpense::factory()->for(test()->bianchi)->forCard($utility)->create(['start_date' => '2026-06-01']);
        PlannedExpense::factory()->for(test()->bianchi)->forCard($utility)->create(['start_date' => '2026-01-01']);

        expect($this->search->planned('expense', $utility, null, null)?->id)->toBe($newest->id);
    });

    test('several: the one whose dates contain the date, not the first one', function (): void {
        $utility = Utility::factory()->for(test()->bianchi)->create();
        $make = fn (string $start, ?string $end) => PlannedExpense::factory()->for(test()->bianchi)->forCard($utility)
            ->create(['start_date' => $start, 'end_date' => $end]);
        $make('2026-06-01', null);
        $middle = $make('2026-01-01', '2026-05-31');
        $make('2025-01-01', '2025-12-31');

        expect($this->search->planned('expense', $utility, null, $this->day)?->id)->toBe($middle->id)
            ->and($this->search->planned('expense', $utility, null, CarbonImmutable::parse('2026-07-01'))?->id)
            ->not->toBe($middle->id);
    });

    test('the dates are inclusive and an open end counts as containing', function (): void {
        $employment = Employment::factory()->for(test()->bianchi)->create();
        $make = fn (string $start, ?string $end) => PlannedIncome::factory()->for(test()->bianchi)
            ->create(['employment_id' => $employment->id, 'start_date' => $start, 'end_date' => $end]);
        $make('2026-06-01', null);
        $ending = $make('2026-01-01', '2026-03-15');
        $old = $make('2025-01-01', null);

        expect($this->search->planned('income', null, $employment, $this->day)?->id)->toBe($ending->id)
            ->and($this->search->planned('income', null, $employment, CarbonImmutable::parse('2026-02-01'))?->id)->toBe($ending->id)
            ->and($this->search->planned('income', null, $employment, CarbonImmutable::parse('2025-02-01'))?->id)->toBe($old->id);
    });

    test('several and none contains the date: the first', function (): void {
        $utility = Utility::factory()->for(test()->bianchi)->create();
        $first = PlannedExpense::factory()->for(test()->bianchi)->forCard($utility)->create(['start_date' => '2026-06-01', 'end_date' => null]);
        PlannedExpense::factory()->for(test()->bianchi)->forCard($utility)->create(['start_date' => '2025-01-01', 'end_date' => '2025-12-31']);

        expect($this->search->planned('expense', $utility, null, CarbonImmutable::parse('2024-01-01'))?->id)->toBe($first->id);
    });

    test('several containing the date: the first of the query order', function (): void {
        $utility = Utility::factory()->for(test()->bianchi)->create();
        $first = PlannedExpense::factory()->for(test()->bianchi)->forCard($utility)->create(['start_date' => '2026-02-01', 'end_date' => null]);
        PlannedExpense::factory()->for(test()->bianchi)->forCard($utility)->create(['start_date' => '2026-01-01', 'end_date' => null]);

        expect($this->search->planned('expense', $utility, null, $this->day)?->id)->toBe($first->id);
    });

    test('a one-off on the utility is not the commitment of a later bill: the recurring one is', function (): void {
        $utility = Utility::factory()->for(test()->bianchi)->create();
        PlannedExpense::factory()->for(test()->bianchi)->forCard($utility)->create(['is_recurring' => false, 'start_date' => '2026-11-30']);
        $recurring = PlannedExpense::factory()->for(test()->bianchi)->forCard($utility)->create(['is_recurring' => true, 'start_date' => '2026-01-10']);

        expect($this->search->planned('expense', $utility, null, CarbonImmutable::parse('2027-01-15'))?->id)->toBe($recurring->id);
    });

    test('only a one-off, far from the date: no commitment; within 20 days: the one-off', function (): void {
        $utility = Utility::factory()->for(test()->bianchi)->create();
        $oneOff = PlannedExpense::factory()->for(test()->bianchi)->forCard($utility)->create(['is_recurring' => false, 'start_date' => '2026-11-30']);

        expect($this->search->planned('expense', $utility, null, CarbonImmutable::parse('2027-01-29')))->toBeNull()
            ->and($this->search->planned('expense', $utility, null, null))->toBeNull()
            ->and($this->search->planned('expense', $utility, null, CarbonImmutable::parse('2026-12-20'))?->id)->toBe($oneOff->id)
            ->and($this->search->planned('expense', $utility, null, CarbonImmutable::parse('2026-12-21')))->toBeNull();
    });

    test('a card of another type with the same id is not the card', function (): void {
        $vehicle = Vehicle::factory()->for(test()->bianchi)->create();
        $utility = Utility::factory()->for(test()->bianchi)->create(['id' => $vehicle->id]);
        PlannedExpense::factory()->for(test()->bianchi)->forCard($vehicle)->create();

        expect($this->search->planned('expense', $utility, null, $this->day))->toBeNull();
    });
});

describe('findPlannedSimilar', function (): void {
    beforeEach(function (): void {
        $this->seven = organizationOf();
        $this->dentist = plannedExpenseOf([
            'title' => 'Cure dentistiche', 'start_date' => '2026-11-30', 'amount' => '480.00',
            'organization_id' => $this->seven->id, 'is_recurring' => false,
        ]);
    });

    test('the one-off planned expense of the same issuer and amount within 20 days', function (): void {
        $found = $this->search->plannedSimilar(480, CarbonImmutable::parse('2026-11-20'), $this->seven->id);

        expect($found?->id)->toBe($this->dentist->id)
            ->and($found?->title)->toBe('Cure dentistiche');
    });

    test('another amount, another issuer, more than 20 days or a recurring one: none', function (): void {
        $day = CarbonImmutable::parse('2026-11-30');

        expect($this->search->plannedSimilar(481, $day, $this->seven->id))->toBeNull()
            ->and($this->search->plannedSimilar(480, $day, organizationOf()->id))->toBeNull()
            ->and($this->search->plannedSimilar(480, CarbonImmutable::parse('2026-11-09'), $this->seven->id))->toBeNull();

        $this->dentist->update(['is_recurring' => true]);
        expect($this->search->plannedSimilar(480, $day, $this->seven->id))->toBeNull();
    });

    test('without the issuer, only within 3 days', function (): void {
        expect($this->search->plannedSimilar(480, CarbonImmutable::parse('2026-11-27'), null))->not->toBeNull()
            ->and($this->search->plannedSimilar(480, CarbonImmutable::parse('2026-11-26'), null))->toBeNull();
    });

    test('the closest date wins, then the lower id', function (): void {
        $closer = plannedExpenseOf([
            'start_date' => '2026-11-22', 'amount' => '480.00', 'organization_id' => $this->seven->id, 'is_recurring' => false,
        ]);

        expect($this->search->plannedSimilar(480, CarbonImmutable::parse('2026-11-21'), $this->seven->id)?->id)->toBe($closer->id);
    });
});

test('a duplicate of another family is not found', function (): void {
    $planned = plannedExpenseOf();
    CurrentFamily::using($this->rossi->id, function () use ($planned): void {
        Expense::factory()->for($this->rossi)->create(['planned_expense_id' => $planned->id, 'date' => '2026-03-15', 'amount' => 50]);
    });

    expect($this->search->duplicate('expense', $planned, $this->day))->toBeNull()
        ->and($this->search->similar('expense', 50, $this->day, null))->toBeNull();
});
