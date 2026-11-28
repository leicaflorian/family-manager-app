<?php

declare(strict_types=1);

use App\Data\Reading\ImportChoices;
use App\Data\Reading\ProposalData;
use App\Data\Reading\RecordDraftData;
use App\Data\Reading\SupplyProposalData;
use App\Enums\DueMode;
use App\Enums\RecordKind;
use App\Enums\Role;
use App\Enums\UtilityStatus;
use App\Models\DocumentCategory;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\File;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Services\Reading\Proposal;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Support\EnelBill;

/**
 * The proposal of the dual-fuel bill of the Bianchi, in Italian.
 *
 * @param  array<string, mixed>  $changes  fields of the reading to change
 * @param  array<mixed>  $choices  the choices of the person, as the page sends them
 */
function dualFuelProposal(array $changes = [], array $choices = []): ProposalData
{
    $file = File::factory()->for(test()->bianchi)->read(EnelBill::answer($changes))->create();

    return resolve(Proposal::class)->for($file, ImportChoices::fromArray($choices), [], 'it');
}

/**
 * The id of a row of the Bianchi found by a column.
 *
 * @param  class-string<Model>  $model
 */
function dualFuelId(string $model, string $column, string $value): int
{
    return insideBianchi(fn (): int => $model::query()->where($column, $value)->sole()->getKey());
}

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-27 10:00', 'Europe/Rome'));
    $this->seed(BianchiFamilySeeder::class);
    $this->bianchi = Family::query()->where('name', 'Bianchi')->sole();
    $this->utilities = EnelBill::bianchi();
    $this->luce = dualFuelId(PlannedExpense::class, 'title', 'Luce');
    $this->gas = dualFuelId(PlannedExpense::class, 'title', 'Gas');
});

afterEach(function (): void {
    CurrentFamily::clear();
    Date::setTestNow();
});

test('each supply proposes its utility and its expense, already paid', function (): void {
    $proposal = dualFuelProposal();
    [$electricity, $gas] = $proposal->supplies;
    $account = dualFuelId(FinancialAccount::class, 'name', 'Conto BPER');

    expect($proposal->supplies)->toHaveCount(2)
        ->and($electricity)->toBeInstanceOf(SupplyProposalData::class)
        ->and([$electricity->index, $electricity->utility_type, $electricity->supply_point_code, $electricity->amount])->toBe([0, 'electricity', EnelBill::POD, 96.25])
        ->and($electricity->heading)->toBe('Luce · POD IT 001 E 87654321')
        ->and($electricity->record?->kind)->toBe(RecordKind::Utility)
        ->and($electricity->record?->existing['id'])->toBe($this->utilities['electricity']->id)
        ->and($electricity->record?->suggested)->toBeTrue()
        ->and($electricity->movement?->values->amount)->toBe(96.25)
        ->and($electricity->movement?->values->lead['id'])->toBe($this->luce)
        ->and($electricity->movement?->values->title)->toBe('Luce novembre 2026')
        ->and($electricity->movement?->values->date)->toBe('2026-11-21')
        ->and($electricity->movement?->values->financial_account_id)->toBe($account)
        ->and($electricity->movement?->due?->suggested)->toBeFalse()
        ->and($electricity->movement?->suggested)->toBeTrue()
        ->and($gas->heading)->toBe('Gas · PDR 00881234567890')
        ->and($gas->record?->existing['id'])->toBe($this->utilities['gas']->id)
        ->and($gas->movement?->values->amount)->toBe(69.40)
        ->and($gas->movement?->values->lead['id'])->toBe($this->gas)
        ->and($gas->movement?->values->title)->toBe('Gas novembre 2026')
        ->and($proposal->movement)->toBeNull()
        ->and($proposal->consumption)->toBeNull()
        ->and(array_map(fn (RecordDraftData $draft): RecordKind => $draft->kind, $proposal->records))->not->toContain(RecordKind::Utility)
        ->and($proposal->document->title)->toBe('Bolletta luce e gas novembre 2026');
});

test('before the deadline both are to pay, on the due date of their planned expense', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-10 10:00', 'Europe/Rome'));

    [$electricity, $gas] = dualFuelProposal()->supplies;

    expect($electricity->movement?->due?->suggested)->toBeTrue()
        ->and($electricity->movement?->due?->mode)->toBe(DueMode::Exception)
        ->and($electricity->movement?->due?->occurrence)->toBe('2026-11-23')
        ->and($electricity->movement?->due?->date)->toBe('2026-11-21')
        ->and($gas->movement?->due?->suggested)->toBeTrue()
        ->and($gas->movement?->due?->mode)->toBe(DueMode::Exception)
        ->and($gas->movement?->due?->occurrence)->toBe('2026-11-30')
        ->and($gas->movement?->due?->date)->toBe('2026-11-21');
});

test('each supply proposes its consumption, with its own amount', function (): void {
    [$electricity, $gas] = dualFuelProposal()->supplies;

    expect($electricity->consumption?->values->toArray())->toBe([
        'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'quantity' => 201.0, 'unit' => 'kwh', 'amount' => 96.25, 'is_estimated' => false,
    ])
        ->and($electricity->consumption?->utility['id'])->toBe($this->utilities['electricity']->id)
        ->and($electricity->consumption?->priceWarning)->toBeFalse()
        ->and($gas->consumption?->values->toArray())->toBe([
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'quantity' => 48.0, 'unit' => 'smc', 'amount' => 69.40, 'is_estimated' => false,
        ])
        ->and($gas->consumption?->utility['id'])->toBe($this->utilities['gas']->id)
        ->and($gas->consumption?->priceWarning)->toBeFalse();
});

test('a gas utility without codes is not taken: the box creates one, unticked, and says why', function (): void {
    $this->utilities['gas']->update(['supply_point_code' => null, 'customer_code' => null]);
    $category = insideBianchi(fn (): int => ExpenseCategory::query()->where('name', 'Gas')->whereRelation('parent', 'name', 'Bollette e utenze')->sole()->id);

    $gas = dualFuelProposal()->supplies[1];

    expect($gas->record?->existing)->toBeNull()
        ->and($gas->record?->title)->toBe("l'utenza Gas · Enel Energia")
        ->and($gas->record?->values['supply_point_code'])->toBe(EnelBill::PDR)
        ->and($gas->record?->values['customer_code'])->toBe(EnelBill::CUSTOMER)
        ->and($gas->record?->suggested)->toBeFalse()
        ->and($gas->record?->hint)->toBe("Hai già un'utenza del gas: spunta se è un'altra fornitura.")
        ->and($gas->movement?->values->lead)->toBeNull()
        ->and($gas->movement?->values->category_id)->toBe($category)
        ->and($gas->movement?->values->title)->toBe('Bolletta gas novembre 2026');

    $this->utilities['gas']->update(['status' => UtilityStatus::Terminated]);
    $gas = dualFuelProposal()->supplies[1];

    expect($gas->record?->suggested)->toBeTrue()->and($gas->record?->hint)->toBeNull();
});

test('the gas utility picked by hand is linked, and its expense follows its planned expense', function (): void {
    $this->utilities['gas']->update(['supply_point_code' => null, 'customer_code' => null]);

    $gas = dualFuelProposal(choices: ['supplies' => [1 => ['record' => true, 'targets' => ['record' => $this->utilities['gas']->id]]]])->supplies[1];

    expect($gas->record?->existing['id'])->toBe($this->utilities['gas']->id)
        ->and($gas->record?->hint)->toBeNull()
        ->and($gas->record?->suggested)->toBeTrue()
        ->and($gas->movement?->values->lead['id'])->toBe($this->gas)
        ->and($gas->consumption?->utility['id'])->toBe($this->utilities['gas']->id);
});

test('a correction of the amount changes only its supply', function (): void {
    [$electricity, $gas] = dualFuelProposal(choices: ['supplies' => [0 => ['overrides' => ['movement' => ['amount' => 90]]]]])->supplies;

    expect($electricity->movement?->values->amount)->toBe(90.0)
        ->and($electricity->consumption?->values->amount)->toBe(90.0)
        ->and($gas->movement?->values->amount)->toBe(69.40);
});

test('an expense already recorded for a supply is linked', function (): void {
    $expense = insideBianchi(fn (): Expense => Expense::factory()->for($this->bianchi)->create([
        'title' => 'Bolletta gas', 'amount' => 69.40, 'date' => '2026-11-21',
        'organization_id' => Organization::query()->where('name', 'Enel Energia')->sole()->id,
    ]));

    [$electricity, $gas] = dualFuelProposal()->supplies;

    expect($gas->movement?->duplicate['id'])->toBe($expense->id)
        ->and($electricity->movement?->duplicate)->toBeNull();
});

test('a new utility asked by hand is proposed ticked, without the line about the other supply', function (): void {
    $gas = dualFuelProposal(choices: ['supplies' => [1 => ['record' => true, 'targets' => ['record' => 'new']]]])->supplies[1];

    expect($gas->record?->existing)->toBeNull()
        ->and($gas->record?->values['supply_point_code'])->toBe(EnelBill::PDR)
        ->and($gas->record?->suggested)->toBeTrue()
        ->and($gas->record?->hint)->toBeNull();
});

test('two supplies with the same code: the second does not take the utility again, and says there is one already', function (): void {
    $second = EnelBill::electricity(['amount' => 30.0]);

    [$first, $other] = dualFuelProposal(['supplies' => [EnelBill::electricity(), $second]])->supplies;

    expect($first->record?->existing['id'])->toBe($this->utilities['electricity']->id)
        ->and($other->record?->existing)->toBeNull()
        ->and($other->record?->suggested)->toBeFalse()
        ->and($other->record?->hint)->toBe("Hai già un'utenza della luce: spunta se è un'altra fornitura.");
});

test('amounts of the supplies that do not add up to the total block nothing', function (): void {
    $proposal = dualFuelProposal(['total_amount' => 170.0]);
    [$electricity, $gas] = $proposal->supplies;

    expect(preg_replace('/\s/u', ' ', $proposal->document->found[1]->value))->toBe('170,00 € · luce 96,25 €, gas 69,40 €')
        ->and($proposal->warnings)->toBe([])
        ->and([$electricity->movement?->blocked, $gas->movement?->blocked])->toBe([null, null])
        ->and([$electricity->consumption?->blocked, $gas->consumption?->blocked])->toBe([null, null])
        ->and([$electricity->movement?->values->amount, $gas->movement?->values->amount])->toBe([96.25, 69.40])
        ->and(ImportChoices::fromArray(['supplies' => [0 => ['movement' => true], 1 => ['movement' => true]]])->forSupply(1)->movement)->toBeTrue();
});

test('a member gets the supplies on the page, also without Create the card', function (): void {
    $member = User::factory()->for($this->bianchi)->create(['role' => Role::Member]);
    $file = File::factory()->for($this->bianchi)->read(EnelBill::answer())->create(['to_sort' => true]);

    $this->actingAs($member)->get(route('import', ['files' => (string) $file->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has("proposals.{$file->id}.supplies", 2)
            ->where("proposals.{$file->id}.supplies.1.heading", 'Gas · PDR 00881234567890')
            ->where("proposals.{$file->id}.createCard", false));

    $proposal = dualFuelProposal();
    expect($proposal->withoutCreateCard()->supplies)->toEqual($proposal->supplies);
});

test('someone reading in English in an Italian family gets the Italian categories of the types', function (): void {
    $this->utilities['electricity']->update(['supply_point_code' => null, 'customer_code' => null]);
    $file = File::factory()->for($this->bianchi)->read(EnelBill::answer())->create();
    [$home, $light] = insideBianchi(fn (): array => [
        DocumentCategory::query()->where('name', 'Casa e utenze')->sole()->id,
        ExpenseCategory::query()->where('name', 'Luce')->whereRelation('parent', 'name', 'Bollette e utenze')->sole()->id,
    ]);

    $proposal = resolve(Proposal::class)->for($file, ImportChoices::fromArray([]), [], 'en');

    expect($proposal->document->category_id)->toBe($home)
        ->and($proposal->supplies[0]->movement?->values->category_id)->toBe($light)
        ->and($proposal->supplies[0]->movement?->values->title)->toBe('Electricity bill November 2026');
});

test('a reading saved before the supplies proposes exactly as one saved with none', function (): void {
    $file = File::factory()->for($this->bianchi)->read(EnelBill::answer(['supplies' => []]))->create();
    $withNone = resolve(Proposal::class)->for($file, ImportChoices::fromArray([]), [], 'it')->toArray();

    $reading = $file->reading;
    unset($reading['extraction']['supplies']);
    // Written as an older version of the app saved it, without going through the reading of today
    DB::table('files')->where('id', $file->id)->update(['reading' => json_encode($reading, JSON_THROW_ON_ERROR)]);
    $before = resolve(Proposal::class)->for($file->refresh(), ImportChoices::fromArray([]), [], 'it')->toArray();

    expect($file->reading['extraction'])->not->toHaveKey('supplies')
        ->and($before)->toEqual($withNone)
        ->and($before['supplies'])->toBe([])
        ->and($before['movement']['values']['amount'])->toBe(165.65)
        ->and($before['consumption'])->not->toBeNull()
        ->and($before['document']['title'])->toBe('Bolletta luce novembre 2026');
});

test('the consumption of a supply whose utility box is unticked asks to tick it, not to choose the utility above', function (): void {
    $gas = dualFuelProposal(choices: ['supplies' => [1 => ['record' => false, 'consumption' => true]]])->supplies[1];

    expect($gas->consumption?->blocked)->toBe("Per segnare i consumi spunta l'utenza di questa fornitura.")
        ->and($gas->consumption?->complete)->toBe(['utility']);
});
