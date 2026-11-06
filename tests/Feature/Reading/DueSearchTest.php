<?php

declare(strict_types=1);

use App\Enums\DueMode;
use App\Enums\IntervalUnit;
use App\Models\Expense;
use App\Models\Family;
use App\Models\Organization;
use App\Models\PlannedExpense;
use App\Services\Reading\DueSearch;
use App\Support\CurrentFamily;
use App\Support\Deadline;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->bianchi = Family::factory()->create(['name' => 'Bianchi']);
    CurrentFamily::set($this->bianchi->id);
    $this->search = resolve(DueSearch::class);
});

afterEach(fn () => CurrentFamily::clear());

/**
 * A planned expense of the Bianchi: one-off by default, the dentist.
 *
 * @param  array<string, mixed>  $attributes
 */
function dueOf(array $attributes = []): PlannedExpense
{
    return PlannedExpense::factory()->for(test()->bianchi)->create($attributes + [
        'title' => 'Cure dentistiche',
        'amount' => 480,
        'is_recurring' => false,
        'start_date' => '2026-11-30',
    ]);
}

/**
 * Looks for the unpaid due dates of a paper.
 *
 * @param  float  $amount  the amount
 * @param  string  $date  the day of the paper
 * @param  int|null  $organization  the issuer
 * @param  PlannedExpense|null  $only  the commitment the paper already has
 * @return array{match: ?Deadline, others: int}
 */
function findUnpaid(float $amount, string $date, ?int $organization = null, ?PlannedExpense $only = null): array
{
    return test()->search->unpaid($amount, CarbonImmutable::parse($date), $organization, $only);
}

describe('unpaid', function (): void {
    test('a payment receipt finds the unpaid occurrence of the same amount', function (): void {
        $planned = dueOf();

        $found = findUnpaid(480, '2026-11-28');

        expect($found['match']?->planned->id)->toBe($planned->id)
            ->and($found['match']?->occurrence->date->toDateString())->toBe('2026-11-30')
            ->and($found['match']?->occurrence->originalDate->toDateString())->toBe('2026-11-30')
            ->and($found['others'])->toBe(0);
    });

    test('an occurrence already registered is not found', function (): void {
        Expense::factory()->for($this->bianchi)->create(['planned_expense_id' => dueOf()->id, 'amount' => 480, 'date' => '2026-11-28']);

        expect(findUnpaid(480, '2026-11-28'))->toBe(['match' => null, 'others' => 0]);
    });

    test('31 days away is not found, 30 is', function (): void {
        dueOf();

        expect(findUnpaid(480, '2027-01-01')['match'])->toBeNull()
            ->and(findUnpaid(480, '2026-12-30')['match'])->not->toBeNull();
    });

    test('a different amount is not found, to the cent', function (): void {
        dueOf();

        expect(findUnpaid(480.01, '2026-11-28')['match'])->toBeNull();
    });

    test('an occurrence of another organization is left out, one with none stays', function (): void {
        $seven = Organization::factory()->for($this->bianchi)->create();
        $three = Organization::factory()->for($this->bianchi)->create();
        dueOf(['organization_id' => $seven->id]);

        expect(findUnpaid(480, '2026-11-28', $three->id)['match'])->toBeNull()
            ->and(findUnpaid(480, '2026-11-28')['match'])->not->toBeNull();

        PlannedExpense::query()->delete();
        dueOf();

        expect(findUnpaid(480, '2026-11-28', $three->id)['match'])->not->toBeNull();
    });

    test('with two equal occurrences the closest one wins and the other is counted', function (): void {
        $near = dueOf();
        dueOf(['title' => 'Altra', 'start_date' => '2026-12-20']);

        $found = findUnpaid(480, '2026-11-28');

        expect($found['match']?->planned->id)->toBe($near->id)
            ->and($found['others'])->toBe(1);
    });

    test('the same organization comes before the closest date', function (): void {
        $three = Organization::factory()->for($this->bianchi)->create();
        dueOf();
        $same = dueOf(['title' => 'Altra', 'start_date' => '2026-12-20', 'organization_id' => $three->id]);

        expect(findUnpaid(480, '2026-11-28', $three->id)['match']?->planned->id)->toBe($same->id);
    });

    test('a recurring commitment gives the occurrence of the month, exceptions applied', function (): void {
        $luce = dueOf(['title' => 'Luce', 'amount' => 95, 'is_recurring' => true, 'interval' => 1, 'interval_unit' => IntervalUnit::Month, 'start_date' => '2026-01-10']);
        Expense::factory()->for($this->bianchi)->create(['planned_expense_id' => $luce->id, 'amount' => 95, 'date' => '2026-10-12', 'occurrence_date' => '2026-10-10']);

        $match = findUnpaid(95, '2026-11-12')['match'];

        expect($match?->planned->id)->toBe($luce->id)
            ->and($match?->occurrence->date->toDateString())->toBe('2026-11-10')
            ->and($match?->occurrence->originalDate->toDateString())->toBe('2026-11-10');
    });

    test('a due date is paid when a movement names its original date, not when dates are near', function (): void {
        $luce = dueOf(['title' => 'Luce', 'amount' => 95, 'is_recurring' => true, 'start_date' => '2026-01-10']);
        Expense::factory()->for($this->bianchi)->create(['planned_expense_id' => $luce->id, 'amount' => 95, 'date' => '2026-11-12', 'occurrence_date' => '2026-11-10']);

        expect(findUnpaid(95, '2026-11-12')['match']?->occurrence->originalDate->toDateString())->toBe('2026-12-10');
    });

    test('limited to a commitment: the occurrences of the others are not paid, even with the same amount', function (): void {
        $dentist = dueOf();
        $luce = dueOf(['title' => 'Luce', 'amount' => 120, 'is_recurring' => true, 'interval' => 2, 'start_date' => '2026-01-10']);

        expect(findUnpaid(480, '2026-11-28', null, $luce)['match'])->toBeNull()
            ->and(findUnpaid(480, '2026-11-28', null, $dentist)['match']?->planned->id)->toBe($dentist->id);
    });

    test('no planned expenses: nothing is found', function (): void {
        expect(findUnpaid(480, '2026-11-28'))->toBe(['match' => null, 'others' => 0]);
    });

    test('a planned expense of another family is not found', function (): void {
        PlannedExpense::factory()->create(['title' => 'Other', 'amount' => 480, 'is_recurring' => false, 'start_date' => '2026-11-30']);

        expect(findUnpaid(480, '2026-11-28')['match'])->toBeNull();
    });
});

describe('branch', function (): void {
    test('a recurring commitment gives an exception on its occurrence', function (): void {
        $luce = dueOf(['title' => 'Luce', 'amount' => 95, 'is_recurring' => true, 'interval' => 2, 'interval_unit' => IntervalUnit::Month, 'start_date' => '2026-01-10']);

        $branch = $this->search->branch($luce, CarbonImmutable::parse('2026-11-13'), 95, null);

        expect($branch['mode'])->toBe(DueMode::Exception)
            ->and($branch['plan']?->id)->toBe($luce->id)
            ->and($branch['occurrence']?->toDateString())->toBe('2026-11-10');
    });

    test('a similar planned expense gives existing', function (): void {
        $dentist = dueOf();

        $branch = $this->search->branch(null, CarbonImmutable::parse('2026-11-28'), 480, null);

        expect($branch['mode'])->toBe(DueMode::Existing)
            ->and($branch['plan']?->id)->toBe($dentist->id)
            ->and($branch['occurrence'])->toBeNull();
    });

    test('a one-off lead is not an exception: it falls to existing or new', function (): void {
        $dentist = dueOf();

        expect($this->search->branch($dentist, CarbonImmutable::parse('2026-11-30'), 480, null)['mode'])->toBe(DueMode::Existing)
            ->and($this->search->branch($dentist, CarbonImmutable::parse('2026-11-30'), 10, null)['mode'])->toBe(DueMode::New);
    });

    test('nothing similar gives new', function (): void {
        $branch = $this->search->branch(null, CarbonImmutable::parse('2026-11-28'), 480, null);

        expect($branch)->toBe(['mode' => DueMode::New, 'plan' => null, 'occurrence' => null]);
    });
});
