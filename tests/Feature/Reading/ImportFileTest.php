<?php

declare(strict_types=1);

use App\Actions\Reading\ImportFile;
use App\Actions\Reading\ImportFiles;
use App\Actions\Reading\SaveRecords;
use App\Data\Reading\ImportChoices;
use App\Data\Reading\PolicyGiverData;
use App\Data\Reading\RecordDraftData;
use App\Enums\ExceptionType;
use App\Enums\HealthEventType;
use App\Enums\IntervalUnit;
use App\Enums\OrganizationType;
use App\Enums\RecordKind;
use App\Enums\UtilityType;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\File;
use App\Models\HealthEvent;
use App\Models\Home;
use App\Models\InsurancePolicy;
use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Models\Reminder;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;

/**
 * A file of the Bianchi read with these fields, waiting in *To sort*.
 *
 * @param  array<string, mixed>  $extraction  the fields of the reading
 */
function readWaitingFile(array $extraction): File
{
    return File::factory()->for(test()->family)->read($extraction)->create(['to_sort' => true]);
}

/**
 * Imports a file with the choices of the person (the expense ticked unless told otherwise), as the confirm does.
 *
 * @param  array<mixed>  $choices  the choices, as the page sends them
 * @param  list<PolicyGiverData>  $givers  the files confirmed with this one that give a policy
 * @return array{document: Document, movement: Expense|App\Models\Income|PlannedExpense|null, event: ?HealthEvent, records: array<string, Model>, warnings: list<string>}
 */
function importReadFile(File $file, array $choices = [], array $givers = []): array
{
    return resolve(ImportFile::class)->handle($file, ImportChoices::fromArray(['movement' => true, ...$choices]), $givers);
}

/**
 * A box of a card as the proposal makes it, for the cards saved before and after the document.
 *
 * @param  array<string, mixed>  $values  the columns it is created with
 * @param  array{id: int, title: string}|null  $existing  the card already there, to link
 */
function cardBox(RecordKind $kind, array $values, ?array $existing = null, ?string $title = null): RecordDraftData
{
    return new RecordDraftData($kind, $title ?? $kind->value, $values, true, [], $existing);
}

/**
 * The exceptions of Luce, as type, original date and value.
 *
 * @return list<array{string, string, string}>
 */
function luceExceptions(): array
{
    return PlannedExpenseException::query()->orderBy('id')->get()->map(fn (PlannedExpenseException $exception): array => [
        $exception->type->value,
        $exception->occurrence_date->toDateString(),
        $exception->type === ExceptionType::AmountOverride ? number_format((float) $exception->amount_override, 2, '.', '') : (string) $exception->date_override?->toDateString(),
    ])->all();
}

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-16 10:00', 'Europe/Rome'));
    App::setLocale('it');
    $this->family = Family::factory()->create(['name' => 'Bianchi']);
    CurrentFamily::set($this->family->id);
    $this->dentist = Organization::factory()->for($this->family)->create(['name' => 'Studio dentistico']);
    $this->edison = Organization::factory()->for($this->family)->create(['name' => 'Edison']);
    $this->health = ExpenseCategory::factory()->for($this->family)->create(['name' => 'Salute']);
    $this->invoice = [
        'kind' => 'invoice', 'title' => 'Cure dentistiche', 'organization_id' => $this->dentist->id, 'total_amount' => 480,
        'issue_date' => '2026-10-15', 'due_date' => '2026-11-30', 'expense_category_id' => $this->health->id,
    ];
});

afterEach(function (): void {
    CurrentFamily::clear();
    Date::setTestNow();
    App::setLocale('en');
});

describe('Da pagare: a one-off planned expense', function (): void {
    test('saved: the planned expense with the document, no expense; a second file links to it', function (): void {
        $first = readWaitingFile($this->invoice);

        $result = importReadFile($first);

        $planned = PlannedExpense::query()->sole();
        expect(Expense::query()->count())->toBe(0)
            ->and($planned->title)->toBe('Cure dentistiche')
            ->and((float) $planned->amount)->toBe(480.0)
            ->and($planned->is_recurring)->toBeFalse()
            ->and($planned->start_date?->toDateString())->toBe('2026-11-30')
            ->and($planned->organization_id)->toBe($this->dentist->id)
            ->and($planned->category_id)->toBe($this->health->id)
            ->and($planned->person_id)->toBeNull()
            ->and($result['movement']?->is($planned))->toBeTrue()
            ->and($result['document']->file_id)->toBe($first->id)
            ->and($result['document']->title)->toBe('Fattura Studio dentistico ottobre 2026')
            ->and($result['document']->organization_id)->toBe($this->dentist->id)
            ->and($result['document']->planned_expense_id)->toBe($planned->id)
            ->and($first->fresh()?->to_sort)->toBeFalse();

        // the same invoice, arrived by mail too
        $linked = importReadFile(readWaitingFile($this->invoice));

        expect(PlannedExpense::query()->count())->toBe(1)
            ->and($linked['document']->planned_expense_id)->toBe($planned->id)
            ->and($linked['document']->expenses()->count())->toBe(0)
            ->and($linked['movement']?->is($planned))->toBeTrue();
    });

    test('"Già pagata" chosen (due: false): the expense as today', function (): void {
        $result = importReadFile(readWaitingFile($this->invoice), ['due' => false]);

        $expense = Expense::query()->sole();
        expect(PlannedExpense::query()->count())->toBe(0)
            ->and($expense->title)->toBe('Cure dentistiche')
            ->and((float) $expense->amount)->toBe(480.0)
            ->and($expense->date?->toDateString())->toBe('2026-11-30')
            ->and($expense->organization_id)->toBe($this->dentist->id)
            ->and($result['movement']?->is($expense))->toBeTrue()
            ->and($expense->documents()->pluck('documents.id')->all())->toBe([$result['document']->id]);
    });

    test('no document date, a deadline read: not blocked, the planned expense is created', function (): void {
        $result = importReadFile(readWaitingFile([...$this->invoice, 'issue_date' => null]));

        $planned = PlannedExpense::query()->sole();
        expect($result['movement']?->is($planned))->toBeTrue()
            ->and($planned->start_date?->toDateString())->toBe('2026-11-30')
            ->and((float) $planned->amount)->toBe(480.0)
            ->and($planned->is_recurring)->toBeFalse()
            ->and($result['warnings'])->toBe([]);
    });

    test('no date read at all, the deadline written by the user: not blocked, the planned expense is created', function (): void {
        $file = readWaitingFile([...$this->invoice, 'issue_date' => null, 'due_date' => null]);

        $result = importReadFile($file, ['overrides' => ['movement' => ['due_date' => '2026-12-15']]]);

        $planned = PlannedExpense::query()->sole();
        expect($result['movement']?->is($planned))->toBeTrue()
            ->and($planned->start_date?->toDateString())->toBe('2026-12-15')
            ->and((float) $planned->amount)->toBe(480.0);
    });

    test('the expense not ticked: only the document', function (): void {
        $result = importReadFile(readWaitingFile($this->invoice), ['movement' => false]);

        expect($result['movement'])->toBeNull()
            ->and(PlannedExpense::query()->count())->toBe(0)
            ->and(Expense::query()->count())->toBe(0)
            ->and(Document::query()->count())->toBe(1);
    });
});

describe('Da pagare: the bill of a recurring commitment', function (): void {
    beforeEach(function (): void {
        $this->utility = Utility::factory()->for($this->family)->create([
            'type' => UtilityType::Electricity, 'organization_id' => $this->edison->id, 'supply_point_code' => 'IT001E00000001', 'customer_code' => null,
        ]);
        $this->luce = PlannedExpense::factory()->for($this->family)->create([
            'title' => 'Luce', 'amount' => 120, 'is_estimated' => true, 'is_recurring' => true, 'interval' => 2, 'interval_unit' => IntervalUnit::Month,
            'start_date' => '2026-01-10', 'end_date' => null, 'organization_id' => $this->edison->id, 'card_type' => 'utility',
            'card_id' => $this->utility->id, 'person_id' => null,
        ]);
        $this->bill = [
            'kind' => 'utility_bill', 'organization_id' => $this->edison->id, 'total_amount' => 151.44, 'issue_date' => '2026-10-20',
            'due_date' => '2026-11-13', 'supply_point_code' => 'IT001E00000001',
        ];
    });

    test('two exceptions on the occurrence of 10/11/2026; imported again, they are updated', function (): void {
        $result = importReadFile(readWaitingFile($this->bill));

        expect($result['movement']?->is($this->luce))->toBeTrue()
            ->and($result['warnings'])->toBe([])
            ->and(Expense::query()->count())->toBe(0)
            ->and($result['document']->planned_expense_id)->toBe($this->luce->id)
            ->and($result['document']->utilities()->pluck('utilities.id')->all())->toBe([$this->utility->id])
            ->and(luceExceptions())->toBe([
                ['amount_override', '2026-11-10', '151.44'],
                ['date_override', '2026-11-10', '2026-11-13'],
            ]);

        // the corrected bill, imported again: the occurrence is still the one of 10/11, moved to 13/11
        importReadFile(readWaitingFile([...$this->bill, 'total_amount' => 149.9]));

        expect(luceExceptions())->toHaveCount(2)
            ->and(array_map(fn (array $row): string => $row[2], luceExceptions()))->toBe(['149.90', '2026-11-13']);
    });

    test('a deadline equal to the occurrence: only the amount', function (): void {
        importReadFile(readWaitingFile([...$this->bill, 'due_date' => '2026-11-10']));

        expect(luceExceptions())->toBe([['amount_override', '2026-11-10', '151.44']]);
    });

    test('a commitment ended before the deadline: a one-off planned expense linked to the utility', function (): void {
        $this->luce->update(['end_date' => '2026-06-30']);

        importReadFile(readWaitingFile($this->bill));

        $planned = PlannedExpense::query()->whereKeyNot($this->luce->id)->sole();
        expect($planned->is_recurring)->toBeFalse()
            ->and($planned->start_date?->toDateString())->toBe('2026-11-13')
            ->and((float) $planned->amount)->toBe(151.44)
            ->and($planned->card_type)->toBe('utility')
            ->and($planned->card_id)->toBe($this->utility->id)
            ->and($planned->organization_id)->toBe($this->edison->id);
    });
});

describe('a payment receipt that pays a planned expense', function (): void {
    beforeEach(function (): void {
        Date::setTestNow(CarbonImmutable::parse('2026-11-28 12:00', 'Europe/Rome'));
        $this->dental = PlannedExpense::factory()->for($this->family)->create([
            'title' => 'Cure dentistiche', 'amount' => 480, 'is_recurring' => false, 'start_date' => '2026-11-30', 'organization_id' => $this->dentist->id,
            'card_type' => null, 'card_id' => null,
        ]);
        $this->receipt = [
            'kind' => 'payment_receipt', 'organization_id' => $this->dentist->id, 'total_amount' => 480, 'fee_amount' => 1, 'issue_date' => '2026-11-28',
        ];
    });

    test('found among the unpaid occurrences: the expense with the fee, linked to the planned expense', function (): void {
        $waiting = Document::factory()->for($this->family)->create(['planned_expense_id' => $this->dental->id, 'date' => '2026-10-15']);

        $result = importReadFile(readWaitingFile($this->receipt));

        $expense = Expense::query()->sole();
        expect($expense->planned_expense_id)->toBe($this->dental->id)
            ->and($expense->occurrence_date?->toDateString())->toBe('2026-11-30')
            ->and($expense->title)->toBe('Cure dentistiche novembre 2026')
            ->and((float) $expense->amount)->toBe(481.0)
            ->and((float) $expense->fee_amount)->toBe(1.0)
            ->and($expense->date?->toDateString())->toBe('2026-11-28')
            ->and($result['movement']?->is($expense))->toBeTrue()
            ->and($expense->documents()->orderBy('documents.id')->pluck('documents.id')->all())->toBe([$waiting->id, $result['document']->id]);
    });

    test('a receipt with a commitment pays only that one: not another planned expense with the same amount', function (): void {
        $this->dental->update(['organization_id' => null]);
        $utility = Utility::factory()->for($this->family)->create([
            'type' => UtilityType::Electricity, 'organization_id' => $this->edison->id, 'supply_point_code' => 'IT001E00000001', 'customer_code' => null,
        ]);
        $luce = PlannedExpense::factory()->for($this->family)->create([
            'title' => 'Luce', 'amount' => 120, 'is_estimated' => true, 'is_recurring' => true, 'interval' => 2, 'interval_unit' => IntervalUnit::Month,
            'start_date' => '2026-01-10', 'organization_id' => $this->edison->id, 'card_type' => 'utility', 'card_id' => $utility->id,
        ]);

        importReadFile(readWaitingFile([...$this->receipt, 'organization_id' => $this->edison->id, 'supply_point_code' => 'IT001E00000001']));

        $expense = Expense::query()->sole();
        expect($expense->planned_expense_id)->toBe($luce->id)
            ->and((float) $expense->amount)->toBe(481.0);
    });

    test('nothing to pay: the expense as today', function (): void {
        $this->dental->delete();

        importReadFile(readWaitingFile($this->receipt));

        $expense = Expense::query()->sole();
        expect((float) $expense->amount)->toBe(481.0)
            ->and((float) $expense->fee_amount)->toBe(1.0)
            ->and($expense->planned_expense_id)->toBeNull();
    });
});

describe('a new issuer created with the file', function (): void {
    beforeEach(function (): void {
        $this->newIssuer = [
            'kind' => 'invoice', 'title' => 'Riparazione caldaia', 'issuer_name' => 'Termoidraulica Rossi', 'organization_type' => OrganizationType::Shop->value,
            'total_amount' => 150, 'issue_date' => '2026-10-10',
        ];
    });

    test('the document and the expense take it, even with the "Ente" menus sent empty by the page', function (): void {
        $result = importReadFile(readWaitingFile($this->newIssuer), [
            'due' => false, 'records' => ['organization'],
            'document' => ['organization_id' => null], 'overrides' => ['movement' => ['organization_id' => null]],
        ]);

        $organization = Organization::query()->where('name', 'Termoidraulica Rossi')->sole();
        expect($organization->types?->all())->toBe([OrganizationType::Shop])
            ->and($result['records']['organization']->is($organization))->toBeTrue()
            ->and(Expense::query()->sole()->organization_id)->toBe($organization->id)
            ->and($result['document']->organization_id)->toBe($organization->id);
    });

    test('an issuer picked by hand stays', function (): void {
        $result = importReadFile(readWaitingFile($this->newIssuer), [
            'due' => false, 'records' => ['organization'],
            'document' => ['organization_id' => $this->edison->id], 'overrides' => ['movement' => ['organization_id' => $this->edison->id]],
        ]);

        expect(Expense::query()->sole()->organization_id)->toBe($this->edison->id)
            ->and($result['document']->organization_id)->toBe($this->edison->id);
    });
});

describe('saveRecords', function (): void {
    test('an organization picked by hand becomes the supplier of the new utility', function (): void {
        $saved = resolve(SaveRecords::class)->before([
            cardBox(RecordKind::Organization, ['name' => 'Edison'], ['id' => $this->edison->id, 'title' => 'Edison']),
            cardBox(RecordKind::Utility, ['type' => 'electricity', 'organization_id' => null]),
        ], [RecordKind::Organization, RecordKind::Utility]);

        $utility = Utility::query()->sole();
        expect($saved['organization'])->toBe($this->edison->id)
            ->and($utility->type)->toBe(UtilityType::Electricity)
            ->and($utility->organization_id)->toBe($this->edison->id)
            ->and($saved['links'])->toBe([['type' => 'utility', 'id' => $utility->id]]);
    });

    test('a vehicle picked by hand goes on the new policy, and on the intervention instead of the home', function (): void {
        $clio = Vehicle::factory()->for($this->family)->create(['name' => 'Clio']);
        $home = Home::factory()->for($this->family)->create();
        $boxes = [
            cardBox(RecordKind::Vehicle, ['name' => 'Clio'], ['id' => $clio->id, 'title' => 'Clio']),
            cardBox(RecordKind::Policy, ['title' => 'Assicurazione auto', 'type' => 'car', 'vehicle_id' => null]),
            cardBox(RecordKind::Maintenance, ['title' => 'Tagliando', 'date' => '2026-09-20', 'vehicle_id' => null, 'home_id' => $home->id]),
        ];
        $ticked = [RecordKind::Vehicle, RecordKind::Policy, RecordKind::Maintenance];
        $document = Document::factory()->for($this->family)->create();
        $expense = Expense::factory()->for($this->family)->create();

        $before = resolve(SaveRecords::class)->before($boxes, $ticked);
        resolve(SaveRecords::class)->after($boxes, $ticked, $before['ids'], $document, $expense);

        $policy = InsurancePolicy::query()->sole();
        $intervention = MaintenanceRecord::query()->sole();
        expect($policy->title)->toBe('Assicurazione auto')
            ->and($policy->vehicle_id)->toBe($clio->id)
            ->and($intervention->vehicle_id)->toBe($clio->id)
            ->and($intervention->home_id)->toBeNull()
            ->and($intervention->title)->toBe('Tagliando')
            ->and($intervention->expense_id)->toBe($expense->id)
            ->and($intervention->documents()->pluck('documents.id')->all())->toBe([$document->id]);
    });

    test('a policy keeps its home only when it is a home policy', function (): void {
        $home = Home::factory()->for($this->family)->create();

        resolve(SaveRecords::class)->before([
            cardBox(RecordKind::Policy, ['title' => 'Assicurazione casa', 'policy_number' => 'H-77', 'type' => 'home', 'home_id' => $home->id]),
            cardBox(RecordKind::Policy, ['title' => 'Assicurazione vita', 'policy_number' => '123456', 'type' => 'life', 'home_id' => $home->id]),
        ], [RecordKind::Policy]);

        expect(InsurancePolicy::query()->orderBy('id')->get()->map(fn (InsurancePolicy $policy): array => [$policy->policy_number, $policy->home_id])->all())
            ->toBe([['H-77', $home->id], ['123456', null]]);
    });

    test('a record missing a required field is not created, and says what is missing', function (): void {
        $save = fn () => resolve(SaveRecords::class)->before(
            [cardBox(RecordKind::Policy, ['title' => 'Polizza', 'type' => null], title: 'la polizza 123')],
            [RecordKind::Policy],
        );

        expect($save)->toThrow(ValidationException::class, 'Per creare la polizza 123 serve il tipo: inseriscili o togli la spunta.')
            ->and(InsurancePolicy::query()->count())->toBe(0);
    });

    test('a policy whose title was cleared is not created', function (): void {
        $save = fn () => resolve(SaveRecords::class)->before(
            [cardBox(RecordKind::Policy, ['title' => null, 'type' => 'car'], title: 'la polizza 123')],
            [RecordKind::Policy],
        );

        expect($save)->toThrow(ValidationException::class, 'Per creare la polizza 123 serve il titolo: inseriscili o togli la spunta.')
            ->and(InsurancePolicy::query()->count())->toBe(0);
    });
});

describe('all or nothing', function (): void {
    test('a card that cannot be saved saves nothing of the file', function (): void {
        $file = readWaitingFile([
            'kind' => 'utility_bill', 'issuer_name' => 'Iren', 'organization_type' => OrganizationType::UtilityProvider->value, 'utility_type' => 'gas',
            'total_amount' => 80, 'issue_date' => '2026-10-10', 'supply_point_code' => '00881234567890',
        ]);
        Utility::creating(fn (): never => throw new RuntimeException('The disk is full'));

        $failures = resolve(ImportFiles::class)->handle([['file_id' => $file->id, 'choices' => ImportChoices::fromArray([
            'movement' => true, 'due' => false, 'records' => ['organization', 'utility'],
        ])]]);

        expect($failures['failures'])->toBe([$file->id => 'Non sono riuscito a salvare questo file.'])
            ->and(Document::query()->count())->toBe(0)
            ->and(Expense::query()->count())->toBe(0)
            ->and(Organization::query()->where('name', 'Iren')->exists())->toBeFalse()
            ->and($file->fresh()?->to_sort)->toBeTrue();
    });

    test('two confirms of the same file make one document and one expense', function (): void {
        $file = readWaitingFile([
            'kind' => 'invoice', 'title' => 'Riparazione caldaia', 'issuer_name' => 'Termoidraulica Rossi', 'total_amount' => 150, 'issue_date' => '2026-10-10',
        ]);
        $entry = ['file_id' => $file->id, 'choices' => ImportChoices::fromArray(['movement' => true, 'due' => false, 'records' => ['organization']])];

        $first = resolve(ImportFiles::class)->handle([$entry]);
        $second = resolve(ImportFiles::class)->handle([$entry]);

        expect($first['failures'])->toBe([])
            ->and($second['failures'])->toBe([$file->id => 'Questo file è già stato archiviato.'])
            ->and(Document::query()->count())->toBe(1)
            ->and(Expense::query()->count())->toBe(1)
            ->and(Organization::query()->where('name', 'Termoidraulica Rossi')->count())->toBe(1);
    });

    test('a due date paid in the meantime fails the file with its reason', function (): void {
        Date::setTestNow(CarbonImmutable::parse('2026-11-28 12:00', 'Europe/Rome'));
        $planned = PlannedExpense::factory()->for($this->family)->create([
            'title' => 'Cure dentistiche', 'amount' => 480, 'is_recurring' => false, 'start_date' => '2026-11-30', 'organization_id' => $this->dentist->id,
        ]);
        $file = readWaitingFile(['kind' => 'payment_receipt', 'organization_id' => $this->dentist->id, 'total_amount' => 480, 'fee_amount' => 1, 'issue_date' => '2026-11-28']);
        // Someone records the payment while the person checks the file
        Document::created(function () use ($planned): void {
            Expense::factory()->for($this->family)->create(['planned_expense_id' => $planned->id, 'occurrence_date' => '2026-11-30', 'amount' => 480, 'date' => '2026-11-27']);
        });

        $result = resolve(ImportFiles::class)->handle([['file_id' => $file->id, 'choices' => ImportChoices::fromArray(['movement' => true])]]);

        expect($result['failures'])->toBe([$file->id => 'Questa scadenza è già pagata: scegline un\'altra.'])
            ->and(Document::query()->count())->toBe(0);
    });
});

describe('the confirm under the lock', function (): void {
    test('a due date of a commitment paid in the meantime fails the bill', function (): void {
        $utility = Utility::factory()->for($this->family)->create([
            'type' => UtilityType::Electricity, 'organization_id' => $this->edison->id, 'supply_point_code' => 'IT001E00000001', 'customer_code' => null,
        ]);
        $luce = PlannedExpense::factory()->for($this->family)->create([
            'title' => 'Luce', 'amount' => 120, 'is_recurring' => true, 'interval' => 2, 'interval_unit' => IntervalUnit::Month,
            'start_date' => '2026-01-10', 'organization_id' => $this->edison->id, 'card_type' => 'utility', 'card_id' => $utility->id,
        ]);
        $file = readWaitingFile([
            'kind' => 'utility_bill', 'organization_id' => $this->edison->id, 'total_amount' => 151.44, 'issue_date' => '2026-10-20',
            'due_date' => '2026-11-13', 'supply_point_code' => 'IT001E00000001',
        ]);
        // Someone records the payment of 10/11 while the person checks the bill
        Document::created(function () use ($luce): void {
            Expense::factory()->for($this->family)->create(['planned_expense_id' => $luce->id, 'occurrence_date' => '2026-11-10', 'amount' => 120, 'date' => '2026-11-09']);
        });

        $result = resolve(ImportFiles::class)->handle([['file_id' => $file->id, 'choices' => ImportChoices::fromArray(['movement' => true])]]);

        expect($result['failures'])->toBe([$file->id => 'Questa scadenza è già pagata: scegline un\'altra.'])
            ->and(PlannedExpenseException::query()->count())->toBe(0)
            ->and(Document::query()->count())->toBe(0);
    });

    test('a file already filed is not imported again, even when the import is called alone', function (): void {
        $file = readWaitingFile($this->invoice);
        importReadFile($file, ['due' => false]);

        expect(fn (): array => importReadFile($file, ['due' => false]))->toThrow(ValidationException::class, 'Questo file è già stato archiviato.')
            ->and(Document::query()->count())->toBe(1)
            ->and(Expense::query()->count())->toBe(1);
    });
});

describe('the files confirmed together', function (): void {
    beforeEach(function (): void {
        $this->unipol = Organization::factory()->for($this->family)->create(['name' => 'UnipolSai']);
        $this->marco = Person::factory()->for($this->family)->create(['first_name' => 'Marco']);
        $this->contract = readWaitingFile([
            'kind' => 'insurance', 'organization_id' => $this->unipol->id, 'person_id' => $this->marco->id, 'policy_number' => '40/123456',
            'insurance_type' => 'car', 'issue_date' => '2026-10-01', 'title' => 'Contratto polizza',
        ]);
        $this->set = readWaitingFile([
            'kind' => 'insurance', 'organization_id' => $this->unipol->id, 'person_id' => $this->marco->id, 'insurance_type' => 'car',
            'issue_date' => '2026-10-01', 'title' => 'Set informativo',
        ]);
    });

    test('the contract that creates the policy is saved first, and the set without a number takes it', function (): void {
        $result = resolve(ImportFiles::class)->handle([
            ['file_id' => $this->set->id, 'choices' => ImportChoices::fromArray([])],
            ['file_id' => $this->contract->id, 'choices' => ImportChoices::fromArray(['records' => ['policy']])],
        ]);

        $policy = InsurancePolicy::query()->sole();
        expect($result)->toBe(['failures' => [], 'notices' => []])
            ->and($policy->policy_number)->toBe('40/123456')
            ->and(Document::query()->where('file_id', $this->set->id)->sole()->insurancePolicies()->pluck('insurance_policies.id')->all())->toBe([$policy->id]);
    });

    test('when the contract fails, the set is saved without the policy and says so', function (): void {
        InsurancePolicy::creating(fn (): never => throw new RuntimeException('The disk is full'));

        $result = resolve(ImportFiles::class)->handle([
            ['file_id' => $this->set->id, 'choices' => ImportChoices::fromArray([])],
            ['file_id' => $this->contract->id, 'choices' => ImportChoices::fromArray(['records' => ['policy']])],
        ]);

        expect($result['failures'])->toBe([$this->contract->id => 'Non sono riuscito a salvare questo file.'])
            ->and($result['notices'])->toBe([$this->set->id => ['Non collegato alla polizza 40/123456: la polizza non è stata creata. Collegalo dal form del documento.']])
            ->and(Document::query()->where('file_id', $this->set->id)->sole()->insurancePolicies()->count())->toBe(0);
    });
});

describe('the health event', function (): void {
    beforeEach(function (): void {
        $this->giulia = Person::factory()->for($this->family)->create(['first_name' => 'Giulia']);
        $this->visit = [
            'kind' => 'invoice', 'title' => 'Visita dal dentista', 'organization_id' => $this->dentist->id, 'total_amount' => 120,
            'issue_date' => '2026-10-22', 'person_id' => $this->giulia->id, 'health_type' => HealthEventType::Visit->value,
            'next_checkup_date' => '2027-04-22', 'outcome' => 'Tutto bene',
        ];
    });

    test('the reminder of the next checkup is born from the saved event', function (): void {
        $result = importReadFile(readWaitingFile($this->visit), ['due' => false, 'event' => true]);

        $event = HealthEvent::query()->sole();
        $reminder = Reminder::query()->with('link')->where('is_automatic', true)->where('source_field', 'health_events.next_checkup_date')->sole();
        expect($result['event']?->is($event))->toBeTrue()
            ->and($event->title)->toBe('Visita dal dentista')
            ->and($event->person_id)->toBe($this->giulia->id)
            ->and($event->organization_id)->toBe($this->dentist->id)
            ->and($event->expense_id)->toBe(Expense::query()->sole()->id)
            ->and($event->documents()->pluck('documents.id')->all())->toBe([$result['document']->id])
            ->and($reminder->link?->remindable_id)->toBe($event->id);
    });

    test('an event already there takes only the empty fields, the expense and the document', function (): void {
        $existing = HealthEvent::factory()->for($this->family)->create([
            'title' => 'Dentista di Giulia', 'type' => HealthEventType::Visit, 'person_id' => $this->giulia->id, 'date' => '2026-10-22',
            'organization_id' => null, 'outcome' => 'Carie', 'next_checkup_date' => null, 'expense_id' => null,
        ]);

        $result = importReadFile(readWaitingFile($this->visit), ['due' => false, 'event' => true]);

        $existing->refresh();
        expect(HealthEvent::query()->count())->toBe(1)
            ->and($result['event']?->is($existing))->toBeTrue()
            ->and($existing->title)->toBe('Dentista di Giulia')
            ->and($existing->organization_id)->toBe($this->dentist->id)
            ->and($existing->outcome)->toBe('Carie')
            ->and($existing->next_checkup_date?->toDateString())->toBe('2027-04-22')
            ->and($existing->expense_id)->toBe(Expense::query()->sole()->id)
            ->and($existing->documents()->pluck('documents.id')->all())->toBe([$result['document']->id]);
    });
});
