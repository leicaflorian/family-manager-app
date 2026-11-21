<?php

declare(strict_types=1);

use App\Enums\ConsumptionSource;
use App\Enums\ConsumptionUnit;
use App\Enums\Role;
use App\Enums\UtilityType;
use App\Models\Document;
use App\Models\File;
use App\Models\Organization;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityConsumption;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('local');
    Queue::fake();
    $this->marco = registerFamily();
    $this->laura = User::factory()->for($this->marco->family)->create(['role' => Role::Member, 'email_verified_at' => now()]);
    $this->edison = Organization::factory()->for($this->marco->family)->create(['name' => 'Edison']);
    $this->luce = Utility::factory()->for($this->marco->family)->create([
        'type' => UtilityType::Electricity, 'organization_id' => $this->edison->id, 'supply_point_code' => 'IT001E12345678',
    ]);
});

/**
 * A waiting bill of the Bianchi family, read with a consumption.
 *
 * @param  array<string, mixed>  $reading  what changes in the reading
 */
function importConsumptionBill(array $reading = []): File
{
    return File::factory()->for(test()->marco->family)->read([
        'kind' => 'utility_bill', 'title' => 'Bolletta luce settembre', 'issuer_name' => 'Edison', 'utility_type' => 'electricity',
        'total_amount' => 71.30, 'issue_date' => '2026-10-05', 'due_date' => '2026-10-25',
        'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'supply_point_code' => 'IT001E12345678',
        'consumption_quantity' => 201, 'consumption_unit' => 'kwh', 'consumption_estimated' => false, 'consumption_adjusted' => false,
        ...$reading,
    ])->create(['to_sort' => true, 'original_name' => 'bolletta-edison-settembre.pdf', 'created_by' => test()->marco->id]);
}

/**
 * One entry of `POST import`, the document sent as the page does, `links` included.
 *
 * @param  list<array{type: string, id: int}>  $links  the cards of the document
 * @param  array<string, mixed>  $also  what the file does too
 * @return array<string, mixed>
 */
function importConsumptionEntry(File $file, array $links, array $also = []): array
{
    return [
        'file_id' => $file->id,
        'document' => ['title' => 'Bolletta luce settembre', 'status' => 'active', 'organization_id' => null, 'links' => $links],
        'also' => $also,
    ];
}

test('a bill of the electricity linked to its utility proposes the box ticked with the consumption and saves the row', function (): void {
    $file = importConsumptionBill();

    $this->actingAs($this->marco)->get(route('import', ['files' => (string) $file->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where("proposals.{$file->id}.consumption.suggested", true)
            ->where("proposals.{$file->id}.consumption.utility.id", $this->luce->id)
            ->where("proposals.{$file->id}.consumption.values.quantity", 201)
            ->where("proposals.{$file->id}.consumption.values.period_start", '2026-09-01')
            ->where("proposals.{$file->id}.consumption.values.period_end", '2026-09-30')
            ->where("proposals.{$file->id}.consumption.values.unit", 'kwh')
            ->where("proposals.{$file->id}.consumption.blocked", null));

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
        importConsumptionEntry($file, [['type' => 'utility', 'id' => $this->luce->id]], ['consumption' => true]),
    ]])->assertSessionHasNoErrors()->assertSessionMissing('import_failures');

    $document = Document::query()->where('file_id', $file->id)->sole();
    $row = UtilityConsumption::query()->sole();
    expect($row->utility_id)->toBe($this->luce->id)
        ->and($row->document_id)->toBe($document->id)
        ->and($row->source)->toBe(ConsumptionSource::Reading)
        ->and($row->unit)->toBe(ConsumptionUnit::Kwh)
        ->and((float) $row->quantity)->toBe(201.0)
        ->and((float) $row->amount)->toBe(71.30)
        ->and($row->period_start->toDateString())->toBe('2026-09-01')
        ->and($row->period_end->toDateString())->toBe('2026-09-30')
        ->and($row->is_estimated)->toBeFalse();
});

test('without the key the box follows the proposal and saves the row', function (): void {
    $file = importConsumptionBill();

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
        importConsumptionEntry($file, [['type' => 'utility', 'id' => $this->luce->id]]),
    ]])->assertSessionHasNoErrors()->assertSessionMissing('import_failures');

    expect(UtilityConsumption::query()->count())->toBe(1);
});

test('the box unticked saves no row', function (): void {
    $file = importConsumptionBill();

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
        importConsumptionEntry($file, [['type' => 'utility', 'id' => $this->luce->id]], ['consumption' => false]),
    ]])->assertSessionHasNoErrors()->assertSessionMissing('import_failures');

    expect(UtilityConsumption::query()->count())->toBe(0)
        ->and(Document::query()->count())->toBe(1);
});

test('the box corrected saves the corrected quantity, period and estimate', function (): void {
    $file = importConsumptionBill();

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
        importConsumptionEntry($file, [['type' => 'utility', 'id' => $this->luce->id]], [
            'consumption' => true,
            'overrides' => ['consumption' => ['quantity' => 210, 'is_estimated' => true, 'period_start' => '2026-08-31']],
        ]),
    ]])->assertSessionHasNoErrors()->assertSessionMissing('import_failures');

    $row = UtilityConsumption::query()->sole();
    expect((float) $row->quantity)->toBe(210.0)
        ->and($row->is_estimated)->toBeTrue()
        ->and($row->period_start->toDateString())->toBe('2026-08-31');
});

test('a correction that is not a positive quantity is refused', function (): void {
    $file = importConsumptionBill();

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
        importConsumptionEntry($file, [['type' => 'utility', 'id' => $this->luce->id]], [
            'consumption' => true, 'overrides' => ['consumption' => ['quantity' => -3]],
        ]),
    ]])->assertSessionHasErrors('files.0.also.overrides.consumption.quantity');

    expect(Document::query()->count())->toBe(0);
});

test('without a utility linked the box asks for it and a ticked box is refused', function (): void {
    $file = importConsumptionBill(['supply_point_code' => null]);

    $this->actingAs($this->marco)->get(route('import', ['files' => (string) $file->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where("proposals.{$file->id}.consumption.utility", null)
            ->where("proposals.{$file->id}.consumption.values.unit", 'kwh')
            ->where("proposals.{$file->id}.consumption.complete", ['utility'])
            ->where("proposals.{$file->id}.consumption.suggested", false)
            ->where("proposals.{$file->id}.consumption.blocked", 'To record the consumption choose the utility above.'));

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
        importConsumptionEntry($file, [], ['consumption' => true]),
    ]])->assertSessionHasErrors(['files.0.also.consumption' => 'To record the consumption choose the utility above.']);

    expect(Document::query()->count())->toBe(0)
        ->and(UtilityConsumption::query()->count())->toBe(0);
});

test('the utility created with the same file takes the consumption', function (): void {
    $file = importConsumptionBill(['supply_point_code' => 'IT001E99999999']);

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
        importConsumptionEntry($file, [], ['records' => ['utility'], 'consumption' => true]),
    ]])->assertSessionHasNoErrors()->assertSessionMissing('import_failures');

    $utility = Utility::query()->where('supply_point_code', 'IT001E99999999')->sole();
    expect(UtilityConsumption::query()->sole()->utility_id)->toBe($utility->id);
});

test('a reading in kWh for a gas utility does not tick the box', function (): void {
    $gas = Utility::factory()->for($this->marco->family)->create(['type' => UtilityType::Gas, 'organization_id' => $this->edison->id, 'supply_point_code' => '00881234567890']);
    $file = importConsumptionBill(['utility_type' => 'gas', 'supply_point_code' => '00881234567890']);

    $this->actingAs($this->marco)->get(route('import', ['files' => (string) $file->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where("proposals.{$file->id}.consumption.utility.id", $gas->id)
            ->where("proposals.{$file->id}.consumption.values.unit", 'smc')
            ->where("proposals.{$file->id}.consumption.suggested", false));

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
        importConsumptionEntry($file, [['type' => 'utility', 'id' => $gas->id]]),
    ]])->assertSessionHasNoErrors()->assertSessionMissing('import_failures');

    expect(UtilityConsumption::query()->count())->toBe(0);
});

test('a receipt or an internet bill proposes nothing', function (array $reading): void {
    $file = importConsumptionBill($reading);

    $this->actingAs($this->marco)->get(route('import', ['files' => (string) $file->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where("proposals.{$file->id}.consumption", null));
})->with([
    'receipt' => [['kind' => 'payment_receipt']],
    'internet' => [['utility_type' => 'internet', 'supply_point_code' => null]],
]);

test('a member saves the row', function (): void {
    $file = importConsumptionBill();

    $this->actingAs($this->laura)->post(route('import.store'), ['files' => [
        importConsumptionEntry($file, [['type' => 'utility', 'id' => $this->luce->id]], ['consumption' => true]),
    ]])->assertSessionHasNoErrors()->assertSessionMissing('import_failures');

    expect(UtilityConsumption::query()->sole()->created_by)->toBe($this->laura->id);
});

test('the same bill imported twice makes one row', function (): void {
    $first = importConsumptionBill();
    $second = importConsumptionBill(['total_amount' => 71.30]);

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
        importConsumptionEntry($first, [['type' => 'utility', 'id' => $this->luce->id]], ['consumption' => true]),
        importConsumptionEntry($second, [['type' => 'utility', 'id' => $this->luce->id]], ['consumption' => true]),
    ]])->assertSessionHasNoErrors()->assertSessionMissing('import_failures');

    expect(Document::query()->count())->toBe(2)
        ->and(UtilityConsumption::query()->count())->toBe(1);
});

test('what only the reading has is saved as read', function (): void {
    $file = importConsumptionBill(['consumption_adjusted' => true, 'consumption_energy_amount' => 40.12, 'consumption_f1' => 80, 'consumption_f2' => 60, 'consumption_f3' => 61]);

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
        importConsumptionEntry($file, [['type' => 'utility', 'id' => $this->luce->id]], ['consumption' => true]),
    ]])->assertSessionHasNoErrors()->assertSessionMissing('import_failures');

    $row = UtilityConsumption::query()->sole();
    expect($row->has_adjustments)->toBeTrue()
        ->and((float) $row->energy_amount)->toBe(40.12)
        ->and([(float) $row->quantity_f1, (float) $row->quantity_f2, (float) $row->quantity_f3])->toBe([80.0, 60.0, 61.0]);
});

test('a price per unit out of the usual range is flagged, and the corrected amount of the expense is the one of the row', function (): void {
    $file = importConsumptionBill(['total_amount' => 500]);

    $this->actingAs($this->marco)->get(route('import', ['files' => (string) $file->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where("proposals.{$file->id}.consumption.priceWarning", true)
            ->where("proposals.{$file->id}.consumption.suggested", true));

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
        importConsumptionEntry($file, [['type' => 'utility', 'id' => $this->luce->id]], ['consumption' => true, 'overrides' => ['movement' => ['amount' => 71.30]]]),
    ]])->assertSessionHasNoErrors()->assertSessionMissing('import_failures');

    expect((float) UtilityConsumption::query()->sole()->amount)->toBe(71.30);
});
