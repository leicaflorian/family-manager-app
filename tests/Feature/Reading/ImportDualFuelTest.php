<?php

declare(strict_types=1);

use App\Actions\Reading\ImportFile;
use App\Data\Reading\ImportChoices;
use App\Enums\ExceptionType;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Family;
use App\Models\File;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityConsumption;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Pest\Expectation;
use Tests\Support\EnelBill;

/**
 * The choices of one supply as the page sends them, everything ticked unless told otherwise.
 *
 * @param  array<string, mixed>  $changes  choices to change
 * @return array<string, mixed>
 */
function importDualFuelSupply(array $changes = []): array
{
    return ['movement' => true, 'record' => true, 'consumption' => true, 'due' => null, 'targets' => ['movement' => 'auto', 'record' => 'auto'], ...$changes];
}

/**
 * The choices of the whole bill as the page sends them: no movement of the document, both supplies ticked.
 *
 * @param  array<int, array<string, mixed>>  $supplies  the choices of the supplies to change, by index
 * @return array<string, mixed>
 */
function importDualFuelChoices(array $supplies = []): array
{
    return [
        'movement' => false, 'event' => false, 'records' => [], 'policy' => 'auto', 'card_type' => null,
        'supplies' => [0 => importDualFuelSupply($supplies[0] ?? []), 1 => importDualFuelSupply($supplies[1] ?? [])],
    ];
}

/**
 * The dual-fuel bill of the Bianchi, waiting in *To sort*.
 *
 * @param  array<string, mixed>  $changes  fields of the reading to change
 */
function importDualFuelFile(array $changes = []): File
{
    return File::factory()->for(test()->bianchi)->read(EnelBill::answer($changes))->create(['to_sort' => true]);
}

/**
 * Imports the bill with the choices, as the confirm does.
 *
 * @param  array<string, mixed>  $choices  the choices, as the page sends them
 */
function importDualFuel(File $file, array $choices): Document
{
    return resolve(ImportFile::class)->handle($file, ImportChoices::fromArray($choices), [])['document'];
}

/**
 * The ids of the utilities the document is linked to.
 *
 * @return list<int>
 */
function importDualFuelUtilities(Document $document): array
{
    return DB::table('documentables')->where(['document_id' => $document->id, 'documentable_type' => 'utility'])->orderBy('documentable_id')->pluck('documentable_id')->map(fn (mixed $id): int => (int) $id)->all();
}

/**
 * One entry of `POST import`, the document as the page sends it.
 *
 * @param  array<string, mixed>  $also  what the file does too
 * @return array<string, mixed>
 */
function importDualFuelEntry(File $file, array $also): array
{
    return ['file_id' => $file->id, 'document' => ['title' => 'Bolletta luce e gas novembre 2026', 'status' => 'active', 'links' => []], 'also' => $also];
}

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-27 10:00', 'Europe/Rome'));
    App::setLocale('it');
    $this->seed(BianchiFamilySeeder::class);
    $this->bianchi = Family::query()->where('name', 'Bianchi')->sole();
    $this->utilities = EnelBill::bianchi();
    [$this->luce, $this->gas, $this->account] = insideBianchi(fn (): array => [
        PlannedExpense::query()->where('title', 'Luce')->sole()->id,
        PlannedExpense::query()->where('title', 'Gas')->sole()->id,
        FinancialAccount::query()->where('name', 'Conto BPER')->sole()->id,
    ]);
});

afterEach(function (): void {
    CurrentFamily::clear();
    Date::setTestNow();
    App::setLocale('en');
});

test('after the deadline: one document for both utilities, an expense and a consumption per supply, each paying its due date', function (): void {
    $document = importDualFuel(importDualFuelFile(), importDualFuelChoices());

    insideBianchi(function () use ($document): void {
        $expenses = Expense::query()->whereIn('planned_expense_id', [$this->luce, $this->gas])->whereDate('date', '2026-11-21')->orderBy('id')->get();

        expect($document->title)->toBe('Bolletta luce e gas novembre 2026')
            ->and(importDualFuelUtilities($document))->toBe([$this->utilities['electricity']->id, $this->utilities['gas']->id])
            ->and($expenses)->toHaveCount(2)
            ->and($expenses->map(fn (Expense $expense): array => [
                $expense->title, (float) $expense->amount, $expense->planned_expense_id, $expense->occurrence_date?->toDateString(),
                $expense->financial_account_id, $expense->documents()->pluck('documents.id')->all(),
            ])->all())->toBe([
                ['Luce novembre 2026', 96.25, $this->luce, '2026-11-23', $this->account, [$document->id]],
                ['Gas novembre 2026', 69.40, $this->gas, '2026-11-30', $this->account, [$document->id]],
            ])
            ->and(UtilityConsumption::query()->where('document_id', $document->id)->orderBy('utility_id')->get()->map(fn (UtilityConsumption $row): array => [$row->utility_id, (float) $row->quantity])->all())
            ->toBe([[$this->utilities['electricity']->id, 201.0], [$this->utilities['gas']->id, 48.0]]);
    });
});

test('before the deadline: no expense, each due date takes its amount and the deadline, and the document waits on both', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-10 10:00', 'Europe/Rome'));
    $expensesBefore = insideBianchi(fn (): int => Expense::query()->count());

    $document = importDualFuel(importDualFuelFile(), importDualFuelChoices());

    insideBianchi(function () use ($document, $expensesBefore): void {
        $exceptions = PlannedExpenseException::query()->whereIn('planned_expense_id', [$this->luce, $this->gas])
            ->whereDate('occurrence_date', '>=', '2026-11-23')->orderBy('planned_expense_id')->orderBy('type')->get()
            ->map(fn (PlannedExpenseException $row): array => [
                $row->planned_expense_id, $row->type->value, $row->occurrence_date?->toDateString(),
                $row->type === ExceptionType::AmountOverride ? (float) $row->amount_override : $row->date_override?->toDateString(),
            ])->all();

        expect(Expense::query()->count())->toBe($expensesBefore)
            ->and($exceptions)->toEqualCanonicalizing([
                [$this->luce, ExceptionType::AmountOverride->value, '2026-11-23', 96.25],
                [$this->luce, ExceptionType::DateOverride->value, '2026-11-23', '2026-11-21'],
                [$this->gas, ExceptionType::AmountOverride->value, '2026-11-30', 69.40],
                [$this->gas, ExceptionType::DateOverride->value, '2026-11-30', '2026-11-21'],
            ])
            ->and($document->refresh()->planned_expense_id)->toBe($this->luce)
            ->and($document->waitingPlans()->pluck('planned_expenses.id')->all())->toBe([$this->gas]);
    });
});

test('a gas utility that is not there is created with its codes, linked to the document, and the gas expense is its own', function (): void {
    $this->utilities['gas']->delete();

    $document = importDualFuel(importDualFuelFile(), importDualFuelChoices());

    insideBianchi(function () use ($document): void {
        $gas = Utility::query()->where('supply_point_code', EnelBill::PDR)->sole();
        $expense = Expense::query()->where('amount', 69.40)->whereDate('date', '2026-11-21')->sole();

        expect($gas->id)->not->toBe($this->utilities['gas']->id)
            ->and($gas->customer_code)->toBe(EnelBill::CUSTOMER)
            ->and($gas->organization_id)->toBe(Organization::query()->where('name', 'Enel Energia')->sole()->id)
            ->and(importDualFuelUtilities($document))->toBe([$this->utilities['electricity']->id, $gas->id])
            ->and([$expense->card_type, $expense->card_id])->toBe(['utility', $gas->id])
            ->and(UtilityConsumption::query()->where('document_id', $document->id)->where('utility_id', $gas->id)->exists())->toBeTrue();
    });
});

test('a member confirms the same bill, and everything is created', function (): void {
    $laura = User::query()->where('email', 'laura.bianchi@example.com')->sole();
    $file = importDualFuelFile();

    $this->actingAs($laura)->post(route('import.store'), ['files' => [importDualFuelEntry($file, importDualFuelChoices())]])
        ->assertSessionHasNoErrors()->assertRedirect();

    insideBianchi(function () use ($file): void {
        $document = Document::query()->where('file_id', $file->id)->sole();

        expect(importDualFuelUtilities($document))->toBe([$this->utilities['electricity']->id, $this->utilities['gas']->id])
            ->and(Expense::query()->whereIn('planned_expense_id', [$this->luce, $this->gas])->whereDate('date', '2026-11-21')->count())->toBe(2)
            ->and(UtilityConsumption::query()->where('document_id', $document->id)->count())->toBe(2);
    });
});

test('with the gas group unticked only the electricity is saved', function (): void {
    $document = importDualFuel(importDualFuelFile(), importDualFuelChoices([1 => ['movement' => false, 'record' => false, 'consumption' => false]]));

    insideBianchi(function () use ($document): void {
        expect(importDualFuelUtilities($document))->toBe([$this->utilities['electricity']->id])
            ->and(Expense::query()->whereDate('date', '2026-11-21')->whereIn('planned_expense_id', [$this->luce, $this->gas])->pluck('planned_expense_id')->all())->toBe([$this->luce])
            ->and(UtilityConsumption::query()->where('document_id', $document->id)->pluck('utility_id')->all())->toBe([$this->utilities['electricity']->id]);
    });
});

test('a gas consumption without its quantity, ticked, is refused and nothing is saved', function (): void {
    $file = importDualFuelFile(['supplies' => [EnelBill::electricity(), EnelBill::gas(['consumption_quantity' => null])]]);

    $this->actingAs(User::query()->where('email', 'marco.bianchi@example.com')->sole())
        ->postJson(route('import.store'), ['files' => [importDualFuelEntry($file, importDualFuelChoices())]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files.0.also.supplies.1.consumption'])
        ->assertJsonMissingValidationErrors(['files.0.also.supplies.0.consumption']);

    insideBianchi(fn (): Expectation => expect(Document::query()->where('file_id', $file->id)->exists())->toBeFalse()
        ->and(Expense::query()->whereDate('date', '2026-11-21')->count())->toBe(0));
});

test('a supply expense picked by hand that is gone is refused under its supply', function (): void {
    $file = importDualFuelFile();

    $this->actingAs(User::query()->where('email', 'marco.bianchi@example.com')->sole())
        ->postJson(route('import.store'), ['files' => [importDualFuelEntry($file, importDualFuelChoices([1 => ['targets' => ['movement' => 999999, 'record' => 999999]]]))]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files.0.also.supplies.1.targets.movement', 'files.0.also.supplies.1.targets.record']);
});
