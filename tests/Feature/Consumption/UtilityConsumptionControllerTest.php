<?php

declare(strict_types=1);

use App\Enums\ConsumptionSource;
use App\Enums\ConsumptionUnit;
use App\Enums\Role;
use App\Enums\UtilityType;
use App\Models\Document;
use App\Models\Family;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityConsumption;
use App\Support\CurrentFamily;

beforeEach(function (): void {
    $this->family = Family::factory()->create();
    $this->member = User::factory()->for($this->family)->create(['role' => Role::Member]);
    $this->admin = User::factory()->for($this->family)->create(['role' => Role::Admin]);

    CurrentFamily::using($this->family->id, function (): void {
        $this->utility = Utility::factory()->create(['family_id' => $this->family->id, 'type' => UtilityType::Electricity]);
        $this->row = UtilityConsumption::factory()->create(['family_id' => $this->family->id, 'utility_id' => $this->utility->id]);
    });

    $this->page = route('budget.consumption', ['utility' => $this->utility->id, 'to' => '2026-09', 'month' => '2026-09']);
});

afterEach(fn () => CurrentFamily::clear());

/**
 * The fields of the panel, with the ones a test changes.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function utilityConsumptionFields(array $changes = []): array
{
    return ['period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'quantity' => 290, 'amount' => 86.1, 'is_estimated' => true, ...$changes];
}

/** A utility, a row and a document of another family. */
function utilityConsumptionElsewhere(): array
{
    $other = Family::factory()->create();

    return CurrentFamily::using($other->id, static function () use ($other): array {
        $utility = Utility::factory()->create(['family_id' => $other->id]);

        return [
            $utility,
            UtilityConsumption::factory()->create(['family_id' => $other->id, 'utility_id' => $utility->id]),
            Document::factory()->create(['family_id' => $other->id]),
        ];
    });
}

/**
 * The rows of the family, read inside it.
 *
 * @return list<UtilityConsumption>
 */
function utilityConsumptionRows(int $familyId): array
{
    return CurrentFamily::using($familyId, static fn (): array => UtilityConsumption::query()->orderBy('id')->get()->all());
}

it('lets a member add a consumption by hand, back to the page', function (): void {
    $this->actingAs($this->member)
        ->from($this->page)
        ->post(route('budget.consumption.store', $this->utility), utilityConsumptionFields())
        ->assertSessionHasNoErrors()
        ->assertRedirect($this->page);

    $added = utilityConsumptionRows($this->family->id)[1];

    expect($added->utility_id)->toBe($this->utility->id)
        ->and($added->period_start->toDateString())->toBe('2026-10-01')
        ->and($added->period_end->toDateString())->toBe('2026-10-31')
        ->and((float) $added->quantity)->toBe(290.0)
        ->and((float) $added->amount)->toBe(86.1)
        ->and($added->is_estimated)->toBeTrue()
        ->and($added->unit)->toBe(ConsumptionUnit::Kwh)
        ->and($added->source)->toBe(ConsumptionSource::Manual);
});

it('writes the consumption of a bill of the utility, so it leaves «To check»', function (): void {
    $document = CurrentFamily::using($this->family->id, function (): Document {
        $document = Document::factory()->create(['family_id' => $this->family->id]);
        $this->utility->documents()->attach($document->id);

        return $document;
    });

    $this->actingAs($this->member)
        ->post(route('budget.consumption.store', $this->utility), utilityConsumptionFields(['document_id' => $document->id]))
        ->assertSessionHasNoErrors();

    expect(utilityConsumptionRows($this->family->id)[1]->document_id)->toBe($document->id);
});

it('refuses a document that is not a bill of the utility', function (): void {
    [, , $foreign] = utilityConsumptionElsewhere();
    $loose = CurrentFamily::using($this->family->id, fn (): Document => Document::factory()->create(['family_id' => $this->family->id]));

    foreach ([$foreign->id, $loose->id] as $id) {
        $this->actingAs($this->member)
            ->post(route('budget.consumption.store', $this->utility), utilityConsumptionFields(['document_id' => $id]))
            ->assertSessionHasErrors('document_id');
    }

    expect(utilityConsumptionRows($this->family->id))->toHaveCount(1);
});

it('lets a member correct a read consumption, which becomes written by hand', function (): void {
    $this->actingAs($this->member)
        ->from($this->page)
        ->put(route('budget.consumption.update', $this->row), utilityConsumptionFields(['period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'quantity' => 310, 'amount' => null, 'is_estimated' => false]))
        ->assertSessionHasNoErrors()
        ->assertRedirect($this->page);

    $row = $this->row->fresh();

    expect(utilityConsumptionRows($this->family->id))->toHaveCount(1)
        ->and((float) $row->quantity)->toBe(310.0)
        ->and($row->amount)->toBeNull()
        ->and($row->is_estimated)->toBeFalse()
        ->and($row->source)->toBe(ConsumptionSource::Manual);
});

it('accepts a negative consumption, the credit of an adjustment', function (): void {
    $this->actingAs($this->member)
        ->put(route('budget.consumption.update', $this->row), utilityConsumptionFields(['quantity' => -40]))
        ->assertSessionHasNoErrors();

    expect((float) $this->row->fresh()->quantity)->toBe(-40.0);
});

it('does not let a member delete a consumption', function (): void {
    $this->actingAs($this->member)
        ->delete(route('budget.consumption.destroy', $this->row))
        ->assertForbidden();

    expect(utilityConsumptionRows($this->family->id))->toHaveCount(1);
});

it('lets an admin delete a consumption, back to the page', function (): void {
    $this->actingAs($this->admin)
        ->from($this->page)
        ->delete(route('budget.consumption.destroy', $this->row))
        ->assertRedirect($this->page);

    expect(utilityConsumptionRows($this->family->id))->toBeEmpty();
});

it('answers 404 to a utility or a row of another family, even with a wrong body', function (): void {
    [$utility, $row] = utilityConsumptionElsewhere();

    $this->actingAs($this->admin)->post(route('budget.consumption.store', $utility), ['quantity' => 'x'])->assertNotFound();
    $this->actingAs($this->admin)->put(route('budget.consumption.update', $row), ['quantity' => 'x'])->assertNotFound();
    $this->actingAs($this->admin)->delete(route('budget.consumption.destroy', $row))->assertNotFound();
});

it('answers 404 to a utility without consumption, as an internet line', function (): void {
    $internet = CurrentFamily::using($this->family->id, fn (): Utility => Utility::factory()->create(['family_id' => $this->family->id, 'type' => UtilityType::Internet]));

    $this->actingAs($this->admin)->post(route('budget.consumption.store', $internet), utilityConsumptionFields())->assertNotFound();
});

it('refuses a period that ends before it starts, or lasts more than 400 days', function (): void {
    $this->actingAs($this->member)
        ->post(route('budget.consumption.store', $this->utility), utilityConsumptionFields(['period_start' => '2026-10-31', 'period_end' => '2026-10-01']))
        ->assertSessionHasErrors('period_end');

    $this->actingAs($this->member)
        ->post(route('budget.consumption.store', $this->utility), utilityConsumptionFields(['period_start' => '2025-01-01', 'period_end' => '2026-02-05']))
        ->assertSessionHasErrors(['period_end' => 'The period lasts at most 400 days.']);

    expect(utilityConsumptionRows($this->family->id))->toHaveCount(1);
});

it('refuses a consumption of zero or beyond the cap of the type, and an amount out of range', function (mixed $quantity, mixed $amount, string $field): void {
    $this->actingAs($this->member)
        ->post(route('budget.consumption.store', $this->utility), utilityConsumptionFields(['quantity' => $quantity, 'amount' => $amount]))
        ->assertSessionHasErrors($field);
})->with([
    'zero' => [0, 10, 'quantity'],
    'above the cap' => [20001, 10, 'quantity'],
    'below the cap' => [-20001, 10, 'quantity'],
    'missing' => [null, 10, 'quantity'],
    'negative amount' => [100, -1, 'amount'],
    'huge amount' => [100, 100000000, 'amount'],
]);

it('accepts a quantity at the cap of the type', function (): void {
    $this->actingAs($this->member)
        ->post(route('budget.consumption.store', $this->utility), utilityConsumptionFields(['quantity' => 20000]))
        ->assertSessionHasNoErrors();
});

it('keeps the bands and the energy part while their figures stay the same', function (): void {
    CurrentFamily::using($this->family->id, fn () => $this->row->update(['quantity' => 200, 'amount' => 70, 'energy_amount' => 40, 'quantity_f1' => 90, 'quantity_f2' => 60, 'quantity_f3' => 50, 'has_adjustments' => true]));

    $this->actingAs($this->member)
        ->put(route('budget.consumption.update', $this->row), utilityConsumptionFields(['period_start' => '2026-09-02', 'period_end' => '2026-09-30', 'quantity' => 200, 'amount' => 70]))
        ->assertSessionHasNoErrors();

    $kept = $this->row->fresh();

    expect((float) $kept->quantity_f1)->toBe(90.0)
        ->and((float) $kept->energy_amount)->toBe(40.0)
        ->and($kept->has_adjustments)->toBeTrue();

    $this->actingAs($this->member)
        ->put(route('budget.consumption.update', $this->row), utilityConsumptionFields(['period_start' => '2026-09-02', 'period_end' => '2026-09-30', 'quantity' => 210, 'amount' => 75]))
        ->assertSessionHasNoErrors();

    $changed = $this->row->fresh();

    expect($changed->quantity_f1)->toBeNull()
        ->and($changed->energy_amount)->toBeNull();
});
