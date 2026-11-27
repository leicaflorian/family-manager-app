<?php

declare(strict_types=1);

use App\Data\Reading\ImportChoices;
use App\Data\Reading\ProposalData;
use App\Data\Reading\RecordDraftData;
use App\Data\Reading\SupplyProposalData;
use App\Enums\HealthEventType;
use App\Enums\OrganizationType;
use App\Enums\RecordKind;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\File;
use App\Models\HealthEvent;
use App\Models\Income;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\Utility;
use App\Models\UtilityConsumption;
use App\Services\Reading\Proposal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EnelBill;

beforeEach(function (): void {
    Storage::fake('local');
    Queue::fake();
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 19:04', 'Europe/Rome'));
    $this->admin = registerFamily();
    $this->giulia = Person::factory()->for($this->admin->family)->create(['first_name' => 'Giulia', 'last_name' => 'Bianchi']);
    $this->health = ExpenseCategory::factory()->for($this->admin->family)->create(['name' => 'Salute']);
    $this->luce = Utility::factory()->for($this->admin->family)->create();
});

afterEach(function (): void {
    Date::setTestNow();
});

/**
 * A waiting dentist invoice of the Bianchi: it proposes an expense, a due date, a health event and an organization.
 */
function budgetOffImportInvoice(): File
{
    return File::factory()->for(test()->admin->family)->read([
        'kind' => 'invoice', 'title' => 'Visita dal dentista', 'issuer_name' => 'Studio Ferri', 'organization_type' => OrganizationType::Healthcare->value,
        'total_amount' => 120, 'issue_date' => '2026-10-22', 'due_date' => '2026-11-30', 'person_id' => test()->giulia->id,
        'expense_category_id' => test()->health->id, 'health_type' => HealthEventType::Visit->value,
    ])->create(['to_sort' => true, 'created_by' => test()->admin->id]);
}

/**
 * A waiting electricity bill with a consumption.
 */
function budgetOffImportBill(): File
{
    return File::factory()->for(test()->admin->family)->read([
        'kind' => 'utility_bill', 'title' => 'Bolletta luce settembre', 'issuer_name' => 'Edison', 'utility_type' => 'electricity',
        'total_amount' => 71.30, 'issue_date' => '2026-10-05', 'due_date' => '2026-10-25', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
        'supply_point_code' => 'IT001E12345678', 'consumption_quantity' => 201, 'consumption_unit' => 'kwh', 'consumption_estimated' => false, 'consumption_adjusted' => false,
    ])->create(['to_sort' => true, 'created_by' => test()->admin->id]);
}

/**
 * The proposal of a file, as the page shows it.
 */
function budgetOffImportProposal(File $file): ProposalData
{
    return resolve(Proposal::class)->for($file, ImportChoices::fromArray([]), [], 'it');
}

/**
 * One entry of `POST import`, the document sent as the page does.
 *
 * @param  array<string, mixed>  $also  what the file does too
 * @return array<string, mixed>
 */
function budgetOffImportEntry(File $file, array $also = []): array
{
    return [
        'file_id' => $file->id,
        'document' => ['title' => 'Documento', 'status' => 'active', 'organization_id' => null, 'links' => []],
        'also' => $also,
    ];
}

/**
 * Turns the Budget off for the family of the account.
 */
function budgetOffImportSwitchOff(): void
{
    test()->admin->family->forceFill(['budget_enabled' => false])->save();
}

test('with the Budget on, the reading proposes the expense with its due date and the consumption', function (): void {
    $invoice = budgetOffImportProposal(budgetOffImportInvoice());
    $bill = budgetOffImportProposal(budgetOffImportBill());

    expect($invoice->movement)->not->toBeNull()
        ->and($invoice->movement->due)->not->toBeNull()
        ->and($bill->consumption)->not->toBeNull();
});

test('with the Budget off, the reading proposes no movement, no due date and no consumption, but keeps the event and the cards', function (): void {
    budgetOffImportSwitchOff();
    $invoice = budgetOffImportProposal(budgetOffImportInvoice());
    $bill = budgetOffImportProposal(budgetOffImportBill());

    expect($invoice->movement)->toBeNull()
        ->and($invoice->event)->not->toBeNull()
        ->and(array_map(static fn (RecordDraftData $draft): RecordKind => $draft->kind, $invoice->records))->toContain(RecordKind::Organization)
        ->and($bill->consumption)->toBeNull();
});

test('with the Budget off, each supply of a dual-fuel bill keeps its utility box, without expense or consumption', function (): void {
    budgetOffImportSwitchOff();
    $gas = Utility::factory()->for($this->admin->family)->create(['type' => 'gas', 'supply_point_code' => EnelBill::PDR]);
    $file = File::factory()->for($this->admin->family)->read(EnelBill::answer())->create(['to_sort' => true, 'created_by' => $this->admin->id]);

    $proposal = budgetOffImportProposal($file);

    expect($proposal->supplies)->toHaveCount(2)
        ->and($proposal->supplies[0]->record?->kind)->toBe(RecordKind::Utility)
        ->and($proposal->supplies[1]->record?->existing['id'])->toBe($gas->id)
        ->and(array_map(static fn (SupplyProposalData $supply): array => [$supply->movement, $supply->consumption], $proposal->supplies))->toBe([[null, null], [null, null]])
        ->and($proposal->movement)->toBeNull()
        ->and($proposal->consumption)->toBeNull();
});

test('with the Budget off the page gets proposals without movement', function (): void {
    budgetOffImportSwitchOff();
    $file = budgetOffImportInvoice();

    $this->actingAs($this->admin->fresh())->get(route('import', ['files' => (string) $file->id]))
        ->assertInertia(fn ($page) => $page->where("proposals.{$file->id}.movement", null)->where("proposals.{$file->id}.consumption", null)->has("proposals.{$file->id}.event"));
});

test('with the Budget off, an import that asks for money is refused', function (array $also, string $error): void {
    budgetOffImportSwitchOff();
    $file = budgetOffImportInvoice();

    $this->actingAs($this->admin->fresh())->post(route('import.store'), ['files' => [budgetOffImportEntry($file, $also)]])
        ->assertSessionHasErrors($error);

    expect(Document::query()->count())->toBe(0)->and($file->fresh()->to_sort)->toBeTrue();
})->with([
    'the movement' => [['movement' => true], 'files.0.also.movement'],
    'a correction of the movement' => [['overrides' => ['movement' => ['title' => 'Altro']]], 'files.0.also.overrides.movement'],
    'a due date' => [['due' => true], 'files.0.also.due'],
    'the movement target' => [['targets' => ['movement' => 'new']], 'files.0.also.targets.movement'],
    'the consumption' => [['consumption' => true], 'files.0.also.consumption'],
    'a correction of the consumption' => [['overrides' => ['consumption' => ['quantity' => 10]]], 'files.0.also.overrides.consumption'],
    'the expense of a supply' => [['supplies' => [0 => ['movement' => true]]], 'files.0.also.supplies.0.movement'],
    'a due date of a supply' => [['supplies' => [0 => ['due' => true]]], 'files.0.also.supplies.0.due'],
    'the consumption of a supply' => [['supplies' => [1 => ['consumption' => true]]], 'files.0.also.supplies.1.consumption'],
    'a correction of the expense of a supply' => [['supplies' => [0 => ['overrides' => ['movement' => ['amount' => 10]]]]], 'files.0.also.supplies.0.overrides.movement'],
    'a correction of the consumption of a supply' => [['supplies' => [1 => ['overrides' => ['consumption' => ['quantity' => 10]]]]], 'files.0.also.supplies.1.overrides.consumption'],
    'the expense target of a supply' => [['supplies' => [0 => ['targets' => ['movement' => 'new']]]], 'files.0.also.supplies.0.targets.movement'],
]);

test('with the Budget off, an import as the page sends it saves the document and the event, and no money', function (): void {
    budgetOffImportSwitchOff();
    $file = budgetOffImportInvoice();
    $bill = budgetOffImportBill();

    $this->actingAs($this->admin->fresh())->post(route('import.store'), ['files' => [
        budgetOffImportEntry($file, ['movement' => false, 'event' => true, 'records' => ['organization'], 'targets' => ['event' => 'auto', 'records' => []], 'policy' => 'auto', 'card_type' => null]),
        budgetOffImportEntry($bill, ['movement' => false, 'event' => false, 'records' => [], 'policy' => 'auto', 'card_type' => null]),
    ]])->assertSessionHasNoErrors();

    expect(Document::query()->count())->toBe(2)
        ->and(HealthEvent::query()->count())->toBe(1)
        ->and(Organization::query()->where('name', 'Studio Ferri')->exists())->toBeTrue()
        ->and(Expense::query()->count())->toBe(0)
        ->and(Income::query()->count())->toBe(0)
        ->and(PlannedExpense::query()->count())->toBe(0)
        ->and(UtilityConsumption::query()->count())->toBe(0);
});
