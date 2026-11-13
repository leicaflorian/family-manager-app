<?php

declare(strict_types=1);

use App\Data\CardFieldData;
use App\Data\OptionData;
use App\Data\Reading\DueData;
use App\Data\Reading\ImportChoices;
use App\Data\Reading\PolicyGiverData;
use App\Data\Reading\ProposalData;
use App\Data\Reading\RecordDraftData;
use App\Enums\DueMode;
use App\Enums\HealthEventType;
use App\Enums\InsurancePolicyStatus;
use App\Enums\OrganizationType;
use App\Enums\RecordKind;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Employment;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\File;
use App\Models\FinancialAccount;
use App\Models\HealthEvent;
use App\Models\Home;
use App\Models\InsurancePolicy;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\Subscription;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\Reading\Proposal;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * The proposal of a file read with these fields, in Italian.
 *
 * @param  array<string, mixed>  $extraction  the fields of the reading
 * @param  array<mixed>  $choices  the choices of the person, as the page sends them
 * @param  list<PolicyGiverData>  $givers  the files confirmed with this one that give a policy
 */
function proposalOf(array $extraction, array $choices = [], array $givers = []): ProposalData
{
    $file = File::factory()->for(test()->family)->read($extraction)->create();

    return resolve(Proposal::class)->for($file, ImportChoices::fromArray($choices), $givers, 'it');
}

/**
 * The rows of every table, to tell that nothing was written.
 *
 * @return array<string, int>
 */
function rowCounts(): array
{
    $counts = [];
    foreach (DB::connection()->getSchemaBuilder()->getTableListing() as $table) {
        $counts[$table] = DB::table($table)->count();
    }

    return $counts;
}

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-16 10:00', 'Europe/Rome'));
    $this->family = Family::factory()->create(['name' => 'Bianchi']);
    CurrentFamily::set($this->family->id);
    $this->dentist = Organization::factory()->for($this->family)->create(['name' => 'Studio dentistico']);
    $this->health = ExpenseCategory::factory()->for($this->family)->create(['name' => 'Salute']);
    $this->invoice = [
        'kind' => 'invoice', 'title' => 'Cure dentistiche', 'organization_id' => $this->dentist->id, 'total_amount' => 480,
        'issue_date' => '2026-10-15', 'due_date' => '2026-11-30', 'expense_category_id' => $this->health->id,
    ];
});

afterEach(function (): void {
    CurrentFamily::clear();
    Date::setTestNow();
});

describe('Da pagare: a one-off planned expense', function (): void {
    test('an invoice due after today: "Da pagare" by itself, a new planned expense', function (): void {
        $movement = proposalOf($this->invoice)->movement;

        expect($movement->due)->toEqual(new DueData(suggested: true, date: '2026-11-30', plan: null, occurrence: null, mode: DueMode::New))
            ->and($movement->blocked)->toBeNull()
            ->and($movement->pays)->toBeNull();
    });

    test('the same invoice imported after its deadline: "Già pagata"', function (): void {
        Date::setTestNow(CarbonImmutable::parse('2026-12-02 12:00', 'Europe/Rome'));

        expect(proposalOf($this->invoice)->movement->due)
            ->toEqual(new DueData(suggested: false, date: '2026-11-30', plan: null, occurrence: null, mode: DueMode::New));
    });

    test('"Da pagare" without a deadline: blocked', function (): void {
        $movement = proposalOf([...$this->invoice, 'due_date' => null], ['due' => true])->movement;

        expect($movement->due->suggested)->toBeFalse()
            ->and($movement->due->date)->toBeNull()
            ->and($movement->blocked)->toBe('Scadenza non trovata: per creare la spesa da pagare va inserita.');
    });

    test('no date read at all and "Già pagata": blocked as today', function (): void {
        $movement = proposalOf(
            [...$this->invoice, 'issue_date' => null, 'due_date' => null],
            ['due' => false, 'overrides' => ['movement' => ['due_date' => '2026-12-15']]],
        )->movement;

        expect($movement->blocked)->toBe('Importo o data non trovati: per creare la spesa vanno inseriti.');
    });

    test('the deadline written by the user wins over the reading', function (): void {
        $due = proposalOf($this->invoice, ['due' => true, 'overrides' => ['movement' => ['due_date' => '2026-12-15']]])->movement->due;

        expect($due->date)->toBe('2026-12-15')
            ->and($due->mode)->toBe(DueMode::New);
    });

    test('an expense already recorded is linked as today: no "Da pagare"', function (): void {
        $expense = Expense::factory()->for($this->family)->create([
            'title' => 'Cure dentistiche', 'date' => '2026-11-28', 'amount' => 480, 'fee_amount' => null, 'organization_id' => $this->dentist->id,
        ]);

        $movement = proposalOf($this->invoice)->movement;

        expect($movement->duplicate)->toBe(['id' => $expense->id, 'title' => 'Cure dentistiche', 'date' => '2026-11-28'])
            ->and($movement->due)->toBeNull();
    });

    test('an income has no "Da pagare"', function (): void {
        $payslip = ['kind' => 'payslip', 'total_amount' => 1800, 'issue_date' => '2026-10-27', 'due_date' => '2026-11-30'];

        expect(proposalOf($payslip)->movement->due)->toBeNull();
    });
});

describe('the receipts', function (): void {
    test('a payment receipt that pays an existing expense with no fee proposes the fee', function (): void {
        $before = FinancialAccount::factory()->for($this->family)->create(['name' => 'Conto BPER', 'last_four_digits' => '1111']);
        $card = FinancialAccount::factory()->for($this->family)->create(['name' => 'Carta Laura', 'last_four_digits' => '0937']);
        $expense = Expense::factory()->for($this->family)->create([
            'title' => 'Cure dentistiche', 'date' => '2026-11-25', 'amount' => 480, 'fee_amount' => null,
            'organization_id' => $this->dentist->id, 'category_id' => $this->health->id, 'financial_account_id' => $before->id,
        ]);

        $movement = proposalOf([
            'kind' => 'payment_receipt', 'organization_id' => $this->dentist->id, 'total_amount' => 480, 'fee_amount' => 1,
            'issue_date' => '2026-11-28', 'iban_last_four' => '0937',
        ])->movement;

        expect($movement->duplicate['id'])->toBe($expense->id)
            ->and($movement->changes)->toBe([
                "Importo: 481,00\u{a0}€ (480,00\u{a0}€ più 1,00\u{a0}€ di commissione)",
                'Conto: Carta Laura, prima Conto BPER',
            ])
            ->and($movement->warnings)->toBe([])
            ->and($movement->due)->toBeNull()
            ->and($movement->values->financial_account_id)->toBe($card->id);
    });

    test('a receipt whose net amount differs warns and changes nothing', function (): void {
        $expense = Expense::factory()->for($this->family)->create([
            'title' => 'Cure dentistiche', 'date' => '2026-11-25', 'amount' => 500, 'fee_amount' => null,
            'organization_id' => $this->dentist->id, 'category_id' => $this->health->id,
        ]);

        $movement = proposalOf([
            'kind' => 'payment_receipt', 'organization_id' => $this->dentist->id, 'total_amount' => 480, 'fee_amount' => 1,
            'issue_date' => '2026-11-28',
        ], ['targets' => ['movement' => $expense->id]])->movement;

        expect($movement->duplicate['id'])->toBe($expense->id)
            ->and($movement->changes)->toBe([])
            ->and($movement->warnings)->toBe(["La ricevuta è di 480,00\u{a0}€, la spesa di 500,00\u{a0}€: importo e commissione della spesa non cambiano."]);
    });

    test('a payment receipt linked to an existing expense takes the category of its document', function (): void {
        $receipts = DocumentCategory::factory()->for($this->family)->create(['name' => 'Ricevute']);
        $medical = DocumentCategory::factory()->for($this->family)->create(['name' => 'Spese mediche']);
        $expense = Expense::factory()->for($this->family)->create([
            'title' => 'Cure dentistiche', 'date' => '2026-11-25', 'amount' => 480, 'fee_amount' => null, 'organization_id' => $this->dentist->id,
        ]);
        $uncategorized = Document::factory()->for($this->family)->create(['category_id' => null]);
        $invoice = Document::factory()->for($this->family)->create(['category_id' => $medical->id]);
        $expense->documents()->attach([$uncategorized->id, $invoice->id]);
        $receipt = [
            'kind' => 'payment_receipt', 'organization_id' => $this->dentist->id, 'total_amount' => 480, 'fee_amount' => 1,
            'issue_date' => '2026-11-28', 'document_category_id' => $receipts->id,
        ];

        expect(proposalOf($receipt)->document->category_id)->toBe($medical->id)
            ->and(proposalOf($receipt, ['targets' => ['movement' => 'new']])->document->category_id)->toBe($receipts->id);
    });

    test('a payment receipt that pays an unpaid planned expense proposes the payment', function (): void {
        Date::setTestNow(CarbonImmutable::parse('2026-11-28 12:00', 'Europe/Rome'));
        $planned = PlannedExpense::factory()->for($this->family)->create([
            'title' => 'Cure dentistiche', 'amount' => 480, 'is_recurring' => false, 'start_date' => '2026-11-30', 'organization_id' => $this->dentist->id,
        ]);

        $movement = proposalOf([
            'kind' => 'payment_receipt', 'organization_id' => $this->dentist->id, 'total_amount' => 480, 'fee_amount' => 1, 'issue_date' => '2026-11-28',
        ])->movement;

        expect($movement->duplicate)->toBeNull()
            ->and($movement->due->suggested)->toBeFalse()
            ->and($movement->pays)->toBe(['planned' => $planned->id, 'title' => 'Cure dentistiche', 'date' => '2026-11-30', 'occurrence' => '2026-11-30', 'others' => 0]);
    });

    test('the expenses to pick by hand are those within a month of the date, the closest first', function (): void {
        $near = Expense::factory()->for($this->family)->create(['title' => 'Vicina', 'date' => '2026-11-29', 'amount' => 10]);
        $far = Expense::factory()->for($this->family)->create(['title' => 'Lontana', 'date' => '2026-11-01', 'amount' => 20]);
        Expense::factory()->for($this->family)->create(['title' => 'Fuori', 'date' => '2026-10-30', 'amount' => 30]);

        expect(proposalOf($this->invoice)->movement->candidates)->toBe([
            ['id' => $near->id, 'title' => 'Vicina', 'date' => '2026-11-29', 'amount' => 10.0],
            ['id' => $far->id, 'title' => 'Lontana', 'date' => '2026-11-01', 'amount' => 20.0],
        ]);
    });
});

describe('the whole file', function (): void {
    beforeEach(function (): void {
        Date::setTestNow(CarbonImmutable::parse('2026-10-25 19:04', 'Europe/Rome'));
        $this->giulia = Person::factory()->for($this->family)->create(['first_name' => 'Giulia', 'last_name' => 'Bianchi']);
        $this->card = FinancialAccount::factory()->for($this->family)->create(['name' => 'Carta Laura', 'last_four_digits' => '0937']);
        $this->ferri = [
            'kind' => 'invoice', 'title' => 'Visita dal dentista', 'issuer_name' => 'Studio Ferri', 'organization_type' => OrganizationType::Healthcare->value,
            'total_amount' => 120, 'issue_date' => '2026-10-22', 'iban_last_four' => '0937', 'person_id' => $this->giulia->id,
            'expense_category_id' => $this->health->id, 'health_type' => HealthEventType::Visit->value, 'next_checkup_date' => '2027-04-22',
        ];
    });

    test('the Studio Ferri invoice proposes the expense, the event and the organization', function (): void {
        $proposal = proposalOf($this->ferri);

        expect($proposal->movement->suggested)->toBeTrue()
            ->and($proposal->movement->values->amount)->toBe(120.0)
            ->and($proposal->movement->values->date)->toBe('2026-10-22')
            ->and($proposal->movement->values->financial_account_id)->toBe($this->card->id)
            ->and($proposal->movement->values->category_id)->toBe($this->health->id)
            ->and($proposal->movement->values->person_id)->toBe($this->giulia->id)
            ->and($proposal->movement->duplicate)->toBeNull()
            ->and($proposal->movement->blocked)->toBeNull()
            ->and($proposal->movement->due)->toEqual(new DueData(suggested: false, date: null, plan: null, occurrence: null, mode: DueMode::New));

        expect($proposal->event->suggested)->toBeTrue()
            ->and($proposal->event->values->title)->toBe('Visita dal dentista')
            ->and($proposal->event->values->person_id)->toBe($this->giulia->id)
            ->and($proposal->event->values->date)->toBe('2026-10-22')
            ->and($proposal->event->values->next_checkup_date)->toBe('2027-04-22')
            ->and($proposal->event->existing)->toBeNull()
            ->and($proposal->event->blocked)->toBeNull()
            ->and($proposal->event->reminder)->toBe('Ti ricorderò di prenotare il controllo');

        expect($proposal->records)->toHaveCount(1)
            ->and($proposal->records[0]->kind)->toBe(RecordKind::Organization)
            ->and($proposal->records[0]->title)->toBe("l'ente Studio Ferri")
            ->and($proposal->records[0]->suggested)->toBeTrue()
            ->and($proposal->records[0]->existing)->toBeNull()
            ->and(array_column($proposal->records[0]->candidates, 'label'))->toBe(['Studio dentistico'])
            ->and($proposal->policy)->toBeNull();
    });

    test('the event already there is linked, and a new one needs its fields', function (): void {
        $visit = HealthEvent::factory()->for($this->family)->create([
            'title' => 'Dentista di Giulia', 'person_id' => $this->giulia->id, 'date' => '2026-10-22', 'type' => HealthEventType::Visit,
            'next_checkup_date' => '2027-03-01',
        ]);

        $event = proposalOf($this->ferri)->event;

        expect($event->existing)->toBe(['id' => $visit->id, 'title' => 'Dentista di Giulia', 'date' => '2026-10-22'])
            ->and($event->reminder)->toBeNull()
            ->and(array_column($event->candidates, 'id'))->toBe([$visit->id])
            ->and(proposalOf([...$this->ferri, 'person_id' => null], ['targets' => ['event' => 'new']])->event->blocked)
            ->toBe("Per creare l'evento di salute manca la persona.");
    });

    test('the event already there without a next checkup takes the one read, and the reminder is said', function (): void {
        HealthEvent::factory()->for($this->family)->create([
            'title' => 'Dentista di Giulia', 'person_id' => $this->giulia->id, 'date' => '2026-10-22', 'type' => HealthEventType::Visit,
            'next_checkup_date' => null,
        ]);

        $event = proposalOf($this->ferri)->event;

        expect($event->existing)->not->toBeNull()
            ->and($event->reminder)->toBe('Ti ricorderò di prenotare il controllo')
            ->and(proposalOf([...$this->ferri, 'next_checkup_date' => null])->event->reminder)->toBeNull();
    });

    test('without an issue date the event is dated on the deadline', function (): void {
        $event = proposalOf([...$this->ferri, 'issue_date' => null, 'due_date' => '2026-11-05'])->event;

        expect($event->values->date)->toBe('2026-11-05')
            ->and($event->blocked)->toBeNull();
    });

    test('the cards follow the corrections and the choices of the person', function (): void {
        $ferri = Organization::factory()->for($this->family)->create(['name' => 'Studio Ferri']);
        $receipt = [
            'kind' => 'receipt', 'issuer_name' => 'Studio Ferri', 'total_amount' => 549, 'issue_date' => '2026-10-22',
            'durable_good' => true, 'item_name' => 'Lavatrice', 'item_brand' => 'Bosch', 'item_model' => 'Serie 6',
        ];

        // The menu of the issuer emptied: the issuer found by name is proposed as a card to link
        $empty = ['organization_id' => null];
        $found = proposalOf($receipt, ['document' => $empty])->records;
        $picked = proposalOf($receipt, ['document' => $empty, 'targets' => ['records' => ['organization' => $this->dentist->id]]])->records;
        $new = proposalOf($receipt, [
            'document' => $empty,
            'targets' => ['records' => ['organization' => 'new']],
            'overrides' => ['records' => ['belonging' => ['name' => '']]],
        ])->records;

        expect(array_map(fn (RecordDraftData $record): RecordKind => $record->kind, $found))->toBe([RecordKind::Organization, RecordKind::Belonging])
            ->and($found[0]->existing)->toBe(['id' => $ferri->id, 'title' => 'Studio Ferri'])
            ->and($found[1]->reminder)->toBe('Ti ricorderò prima che finisca la garanzia')
            ->and($found[1]->values['warranty_expiry_date'])->toBe('2028-10-22')
            ->and($picked[0]->existing)->toBe(['id' => $this->dentist->id, 'title' => 'Studio dentistico'])
            ->and($new[0]->existing)->toBeNull()
            ->and($new[1]->missing)->toBe(['il nome'])
            ->and($new[1]->suggested)->toBeFalse();
    });

    test('a new card offers the fields it still needs and the person can write, as the card form draws them', function (): void {
        $policy = proposalOf(['kind' => 'insurance', 'policy_number' => '40/123456', 'issue_date' => '2026-10-01'])->records;
        $typed = proposalOf(['kind' => 'insurance', 'policy_number' => '40/123456', 'issue_date' => '2026-10-01'], [
            'overrides' => ['records' => ['policy' => ['type' => 'car']]],
        ])->records;
        $belonging = array_find(proposalOf([
            'kind' => 'receipt', 'total_amount' => 549, 'issue_date' => '2026-10-22', 'durable_good' => true, 'item_name' => 'Lavatrice',
        ], ['overrides' => ['records' => ['belonging' => ['name' => '']]]])->records, fn (RecordDraftData $record): bool => $record->kind === RecordKind::Belonging);
        $maintenance = proposalOf(['kind' => 'invoice', 'maintenance_type' => 'boiler_service', 'issuer_name' => 'Termoidraulica', 'total_amount' => 90]);

        $type = $policy[0]->fields[0];
        expect($policy[0]->kind)->toBe(RecordKind::Policy)
            ->and(array_map(fn (CardFieldData $field): string => $field->column, $policy[0]->fields))->toBe(['type'])
            ->and($type->kind)->toBe('dropdown')
            ->and(array_column(array_map(fn (OptionData $option): array => $option->toArray(), $type->options), 'value'))->toContain('car')
            ->and($typed[0]->fields)->toBe([])
            ->and(array_map(fn (CardFieldData $field): string => $field->column, $belonging->fields ?? []))->toBe(['name'])
            ->and($belonging?->fields[0]->kind)->toBe('text');

        // The date of an intervention is the document's: it is written in the form of the document, not here
        $draft = array_find($maintenance->records, fn (RecordDraftData $record): bool => $record->kind === RecordKind::Maintenance);
        expect($draft?->missing)->not->toBe([])
            ->and(array_map(fn (CardFieldData $field): string => $field->column, $draft->fields ?? []))->not->toContain('date');
    });

    test('a card picked by hand that is gone is looked for again', function (): void {
        Utility::factory()->for($this->family)->create(['supply_point_code' => 'IT001E00000001', 'organization_id' => $this->dentist->id]);
        $bill = ['kind' => 'utility_bill', 'supply_point_code' => 'IT001E00000001', 'utility_type' => 'electricity', 'total_amount' => 80, 'issue_date' => '2026-10-20'];

        expect(proposalOf($bill, ['targets' => ['records' => ['utility' => 999999]]])->records)->toBe([])
            ->and(array_map(fn (RecordDraftData $record): RecordKind => $record->kind, proposalOf($bill, ['targets' => ['records' => ['utility' => 'new']]])->records))
            ->toBe([RecordKind::Utility]);
    });

    test('a policy picked by hand without a number is named by its title', function (): void {
        $policy = InsurancePolicy::factory()->for($this->family)->create(['title' => 'Assicurazione auto', 'policy_number' => null]);

        $records = proposalOf(['kind' => 'insurance', 'policy_number' => '40/123456', 'insurance_type' => 'car'], ['targets' => ['records' => ['policy' => $policy->id]]])->records;

        expect($records[0]->kind)->toBe(RecordKind::Policy)
            ->and($records[0]->existing)->toBe(['id' => $policy->id, 'title' => 'Assicurazione auto']);
    });

    test('an insurance paper without a number takes the policy of the file confirmed with it', function (): void {
        $giver = new PolicyGiverData('file:9', 'Contratto.pdf', '40/123456', true, null, 'Studio Ferri', null, 'car');

        $policy = proposalOf(['kind' => 'insurance', 'issuer_name' => 'Studio Ferri', 'insurance_type' => 'car'], givers: [$giver])->policy;

        expect($policy)->toEqual(['need' => true, 'giver' => $giver, 'many' => false]);
    });

    test('with two registered policies that fit, it takes none and says so', function (): void {
        $company = Organization::factory()->for($this->family)->create(['name' => 'UnipolSai']);
        InsurancePolicy::factory()->for($this->family)->count(2)->sequence(['policy_number' => 'A1'], ['policy_number' => 'B2'])
            ->create(['organization_id' => $company->id, 'status' => InsurancePolicyStatus::Active, 'person_id' => null]);

        $policy = proposalOf(['kind' => 'insurance', 'organization_id' => $company->id])->policy;

        expect($policy)->toEqual(['need' => true, 'giver' => null, 'many' => true]);
    });

    test('the proposal writes nothing', function (): void {
        $file = File::factory()->for($this->family)->read($this->ferri)->create();
        $before = rowCounts();

        resolve(Proposal::class)->for($file, ImportChoices::fromArray(['movement' => true, 'event' => true, 'records' => ['organization']]), [], 'it');

        expect(rowCounts())->toBe($before);
    });
});

describe('what a box still needs, and where it is written', function (): void {
    beforeEach(function (): void {
        $this->giulia = Person::factory()->for($this->family)->create(['first_name' => 'Giulia', 'last_name' => 'Bianchi']);
        $this->visit = [
            'kind' => 'invoice', 'title' => 'Visita dal dentista', 'total_amount' => 120, 'issue_date' => '2026-10-22',
            'person_id' => $this->giulia->id, 'health_type' => HealthEventType::Visit->value,
        ];
    });

    test('an expense without its amount asks for it in its box', function (): void {
        $movement = proposalOf([...$this->invoice, 'total_amount' => null], ['due' => false])->movement;

        expect($movement->fill)->toBe(['amount'])
            ->and($movement->complete)->toBe([])
            ->and($movement->blocked)->not->toBeNull();
    });

    test('an expense without a date takes the date written in the form of the document', function (): void {
        $movement = proposalOf(
            [...$this->invoice, 'issue_date' => null, 'due_date' => null],
            ['due' => false, 'document' => ['date' => '2026-11-05']],
        )->movement;

        expect($movement->values->date)->toBe('2026-11-05')
            ->and($movement->fill)->toBe([])
            ->and($movement->complete)->toBe([])
            ->and($movement->blocked)->toBeNull();
    });

    test('an expense without any date asks to complete the date of the document', function (): void {
        $movement = proposalOf([...$this->invoice, 'issue_date' => null, 'due_date' => null], ['due' => false])->movement;

        expect($movement->complete)->toBe(['date'])
            ->and($movement->fill)->toBe([]);
    });

    test('an expense to pay without a deadline asks for it in its box', function (): void {
        $movement = proposalOf([...$this->invoice, 'due_date' => null], ['due' => true])->movement;

        expect($movement->fill)->toBe(['due_date'])
            ->and($movement->complete)->toBe([]);
    });

    test('an expense linked to one already recorded needs nothing', function (): void {
        $expense = Expense::factory()->for($this->family)->create(['title' => 'Cure', 'date' => '2026-10-01', 'amount' => 10]);

        $movement = proposalOf([...$this->invoice, 'total_amount' => null], ['targets' => ['movement' => $expense->id]])->movement;

        expect($movement->fill)->toBe([])
            ->and($movement->complete)->toBe([]);
    });

    test('a new health event asks the person and the date in the form of the document', function (): void {
        $event = proposalOf(
            [...$this->visit, 'person_id' => null, 'issue_date' => null],
            ['targets' => ['event' => 'new'], 'event' => true],
        )->event;

        expect($event->complete)->toBe(['person_id', 'date'])
            ->and($event->fill)->toBe([]);
    });

    test('an intervention without a date asks to complete the date of the document', function (): void {
        $records = proposalOf(['kind' => 'invoice', 'maintenance_type' => 'boiler_service', 'issuer_name' => 'Termoidraulica', 'total_amount' => 90])->records;
        $draft = array_find($records, fn (RecordDraftData $record): bool => $record->kind === RecordKind::Maintenance);

        expect($draft?->complete)->toBe(['date'])
            ->and(array_find($records, fn (RecordDraftData $record): bool => $record->kind === RecordKind::Organization)?->complete)->toBe([]);
    });
});

describe('the proposal follows the card of the category', function (): void {
    beforeEach(function (): void {
        $this->giulia = Person::factory()->for($this->family)->create(['first_name' => 'Giulia', 'last_name' => 'Bianchi']);
        $this->homeAndUtilities = DocumentCategory::factory()->for($this->family)->create(['name' => 'Casa e utenze', 'card_type' => 'utility']);
        $this->light = DocumentCategory::factory()->for($this->family)->create(['name' => 'Luce', 'parent_id' => $this->homeAndUtilities->id]);
        $this->healthDocs = DocumentCategory::factory()->for($this->family)->create(['name' => 'Salute', 'card_type' => 'health_event']);
        $this->bill = ['kind' => 'other', 'supply_point_code' => 'IT001E00000001', 'total_amount' => 84.2, 'issue_date' => '2026-10-10', 'expense_category_id' => $this->health->id];
    });

    test('a bill filed under a utility category proposes the utility and the expense in the first category with the utility', function (): void {
        $bills = ExpenseCategory::factory()->for($this->family)->create(['name' => 'Bollette e utenze', 'card_type' => 'utility']);

        $proposal = proposalOf($this->bill, ['document' => ['category_id' => $this->light->id]]);

        expect(array_map(fn (RecordDraftData $record): RecordKind => $record->kind, $proposal->records))->toBe([RecordKind::Utility])
            ->and($proposal->records[0]->cardType)->toBe('utility')
            ->and($proposal->cardType)->toBe('utility')
            ->and($proposal->movement->values->kind)->toBe('expense')
            ->and($proposal->movement->values->category_id)->toBe($bills->id)
            ->and($proposal->createCard)->toBeFalse();
    });

    test('the category the reading chose decides when the person did not change it', function (): void {
        $proposal = proposalOf([...$this->bill, 'document_category_id' => $this->light->id]);

        expect($proposal->cardType)->toBe('utility')
            ->and(array_map(fn (RecordDraftData $record): RecordKind => $record->kind, $proposal->records))->toBe([RecordKind::Utility]);
    });

    test('a payslip filed under a job category proposes the income of the job of the person', function (): void {
        $marco = Person::factory()->for($this->family)->create(['first_name' => 'Marco']);
        $job = Employment::factory()->for($this->family)->create(['person_id' => $marco->id]);
        $work = DocumentCategory::factory()->for($this->family)->create(['name' => 'Lavoro']);
        $payslips = DocumentCategory::factory()->for($this->family)->create(['name' => 'Buste paga', 'parent_id' => $work->id, 'card_type' => 'employment']);

        $proposal = proposalOf(['kind' => 'other', 'total_amount' => 1800, 'issue_date' => '2026-10-27'], [
            'document' => ['category_id' => $payslips->id, 'person_id' => $marco->id],
        ]);

        expect($proposal->movement->values->kind)->toBe('income')
            ->and($proposal->movement->values->employment_id)->toBe($job->id)
            ->and($proposal->movement->suggested)->toBeTrue()
            ->and($proposal->records)->toBe([])
            ->and($proposal->event)->toBeNull()
            ->and($proposal->createCard)->toBeFalse();
    });

    test('a paper without an amount in a job category proposes the income, not ticked', function (): void {
        $marco = Person::factory()->for($this->family)->create(['first_name' => 'Marco']);
        Employment::factory()->for($this->family)->create(['person_id' => $marco->id]);
        $work = DocumentCategory::factory()->for($this->family)->create(['name' => 'Lavoro', 'card_type' => 'employment']);

        $proposal = proposalOf(['kind' => 'other', 'title' => 'Contratto di lavoro', 'issue_date' => '2026-10-01'], [
            'document' => ['category_id' => $work->id, 'person_id' => $marco->id],
        ]);

        expect($proposal->movement->values->kind)->toBe('income')
            ->and($proposal->movement->suggested)->toBeFalse();
    });

    test('a payslip of a person without a job proposes to create the job', function (): void {
        $payslips = DocumentCategory::factory()->for($this->family)->create(['name' => 'Buste paga', 'card_type' => 'employment']);

        $proposal = proposalOf(['kind' => 'payslip', 'total_amount' => 1800, 'issue_date' => '2026-10-27'], [
            'document' => ['category_id' => $payslips->id, 'person_id' => $this->giulia->id],
        ]);

        expect($proposal->movement->values->employment_id)->toBeNull()
            ->and($proposal->records)->toHaveCount(1)
            ->and($proposal->records[0]->kind)->toBe(RecordKind::Card)
            ->and($proposal->records[0]->cardType)->toBe('employment')
            ->and($proposal->records[0]->title)->toBe('il lavoro')
            ->and($proposal->records[0]->values['person_id'])->toBe($this->giulia->id)
            ->and($proposal->records[0]->missing)->toBe([]);
    });

    test('a dentist invoice filed under a health category proposes the health event ticked and the expense', function (): void {
        $invoices = DocumentCategory::factory()->for($this->family)->create(['name' => 'Fatture mediche', 'parent_id' => $this->healthDocs->id]);

        $proposal = proposalOf(
            ['kind' => 'invoice', 'title' => 'Visita dal dentista', 'total_amount' => 120, 'issue_date' => '2026-10-22', 'person_id' => $this->giulia->id],
            ['document' => ['category_id' => $invoices->id]],
        );

        expect($proposal->event)->not->toBeNull()
            ->and($proposal->event->suggested)->toBeTrue()
            ->and($proposal->event->values->title)->toBe('Visita dal dentista')
            ->and($proposal->event->values->person_id)->toBe($this->giulia->id)
            ->and($proposal->event->values->date)->toBe('2026-10-22')
            ->and($proposal->movement->values->kind)->toBe('expense')
            ->and($proposal->movement->suggested)->toBeTrue()
            ->and($proposal->records)->toBe([])
            ->and($proposal->createCard)->toBeFalse();
    });

    test('a health event without a title read takes the title of the document', function (): void {
        $proposal = proposalOf(['kind' => 'other', 'issue_date' => '2026-10-22'], ['document' => ['category_id' => $this->healthDocs->id]]);

        expect($proposal->event)->not->toBeNull()
            ->and($proposal->event->values->title)->toBe($proposal->document->title);
    });

    test('a prescription filed under a health category proposes the health event not ticked', function (): void {
        $proposal = proposalOf(
            ['kind' => 'medical', 'health_type' => HealthEventType::Prescription->value, 'issue_date' => '2026-10-22', 'person_id' => $this->giulia->id],
            ['document' => ['category_id' => $this->healthDocs->id]],
        );

        expect($proposal->cardType)->toBe('health_event')
            ->and($proposal->event)->not->toBeNull()
            ->and($proposal->event->suggested)->toBeFalse();
    });

    test('a deed filed under a home category with no home proposes a new home with the required fields of its card', function (): void {
        $deeds = DocumentCategory::factory()->for($this->family)->create(['name' => 'Rogiti', 'parent_id' => $this->homeAndUtilities->id, 'card_type' => 'home']);

        $proposal = proposalOf(['kind' => 'other', 'issue_date' => '2026-10-01'], ['document' => ['category_id' => $deeds->id]]);

        $card = $proposal->records[0];
        expect($proposal->records)->toHaveCount(1)
            ->and($card->kind)->toBe(RecordKind::Card)
            ->and($card->cardType)->toBe('home')
            ->and($card->title)->toBe("l'abitazione")
            ->and($card->missing)->toBe(['il nome'])
            ->and(array_map(fn (CardFieldData $field): string => $field->column, $card->fields))->toBe(['name'])
            ->and($card->existing)->toBeNull()
            ->and($card->candidates)->toBe([]);
    });

    test('with a home category a plate proposes no vehicle, but the organization stays', function (): void {
        $deeds = DocumentCategory::factory()->for($this->family)->create(['name' => 'Rogiti', 'card_type' => 'home']);

        $proposal = proposalOf(
            ['kind' => 'road_tax', 'plate_number' => 'GH482TK', 'vehicle_brand' => 'Fiat', 'issuer_name' => 'ACI', 'total_amount' => 180, 'issue_date' => '2026-10-01'],
            ['document' => ['category_id' => $deeds->id, 'organization_id' => null]],
        );

        expect(array_map(fn (RecordDraftData $record): RecordKind => $record->kind, $proposal->records))->toBe([RecordKind::Organization, RecordKind::Card]);
    });

    test('a category without a card proposes the same as no category', function (): void {
        $plain = DocumentCategory::factory()->for($this->family)->create(['name' => 'Varie']);
        $without = proposalOf($this->invoice)->toArray();
        $with = proposalOf($this->invoice, ['document' => ['category_id' => $plain->id]])->toArray();

        expect(array_diff_key($with, ['fileId' => true]))->toEqual(array_diff_key($without, ['fileId' => true]))
            ->and($with['cardType'])->toBeNull();
    });

    test('a category of another family proposes the same as no category', function (): void {
        $other = Family::factory()->create();
        $theirs = DocumentCategory::factory()->for($other)->create(['name' => 'Rogiti', 'card_type' => 'home']);
        $without = proposalOf($this->invoice)->toArray();
        $with = proposalOf($this->invoice, ['document' => ['category_id' => $theirs->id]])->toArray();

        expect(array_diff_key($with, ['fileId' => true]))->toEqual(array_diff_key($without, ['fileId' => true]));
    });

    test('a category changed keeps the amount written in the expense', function (): void {
        $proposal = proposalOf($this->bill, ['document' => ['category_id' => $this->light->id], 'overrides' => ['movement' => ['amount' => 99.5]]]);

        expect($proposal->movement->values->amount)->toBe(99.5);
    });

    test('a category with a card that no expense category has keeps the expense category of the reading', function (): void {
        $proposal = proposalOf($this->bill, ['document' => ['category_id' => $this->light->id]]);

        expect($proposal->movement->values->category_id)->toBe($this->health->id);
    });
    test('a school letter of the second child does not link the enrollment of the first child', function (): void {
        $school = Organization::factory()->for($this->family)->create(['name' => 'Scuola Rodari']);
        $first = Person::factory()->for($this->family)->create(['first_name' => 'Luca']);
        Enrollment::factory()->for($this->family)->create(['person_id' => $first->id, 'organization_id' => $school->id]);
        $schoolDocs = DocumentCategory::factory()->for($this->family)->create(['name' => 'Scuola', 'card_type' => 'enrollment']);

        $proposal = proposalOf(['kind' => 'other', 'issue_date' => '2026-10-01'], [
            'document' => ['category_id' => $schoolDocs->id, 'organization_id' => $school->id, 'person_id' => $this->giulia->id],
        ]);

        expect($proposal->records[0]->cardType)->toBe('enrollment')
            ->and($proposal->records[0]->existing)->toBeNull();
    });

    test('the payslip of Marco from the employer of the job of Laura does not link the job of Laura', function (): void {
        $employer = Organization::factory()->for($this->family)->create(['name' => 'Comune di Bologna']);
        $laura = Person::factory()->for($this->family)->create(['first_name' => 'Laura']);
        $marco = Person::factory()->for($this->family)->create(['first_name' => 'Marco']);
        Employment::factory()->for($this->family)->create(['person_id' => $laura->id, 'organization_id' => $employer->id]);
        $payslips = DocumentCategory::factory()->for($this->family)->create(['name' => 'Buste paga', 'card_type' => 'employment']);

        $proposal = proposalOf(['kind' => 'payslip', 'total_amount' => 1800, 'issue_date' => '2026-10-27'], [
            'document' => ['category_id' => $payslips->id, 'organization_id' => $employer->id, 'person_id' => $marco->id],
        ]);

        expect($proposal->records)->toHaveCount(1)
            ->and($proposal->records[0]->cardType)->toBe('employment')
            ->and($proposal->records[0]->existing)->toBeNull();
    });

    test('a document without a person does not link a card of a person', function (): void {
        $netflix = Organization::factory()->for($this->family)->create(['name' => 'Netflix']);
        Subscription::factory()->for($this->family)->create(['person_id' => $this->giulia->id, 'organization_id' => $netflix->id]);
        $subscriptions = DocumentCategory::factory()->for($this->family)->create(['name' => 'Abbonamenti', 'card_type' => 'subscription']);

        $proposal = proposalOf(['kind' => 'other', 'issue_date' => '2026-10-01'], [
            'document' => ['category_id' => $subscriptions->id, 'organization_id' => $netflix->id, 'person_id' => null],
        ]);

        expect($proposal->records[0]->existing)->toBeNull();
    });

    test('a category keeps only the warning of its own card', function (): void {
        $deeds = DocumentCategory::factory()->for($this->family)->create(['name' => 'Rogiti', 'card_type' => 'home']);
        $bill = ['kind' => 'utility_bill', 'supply_point_code' => 'IT001E00000009', 'utility_type' => 'electricity', 'total_amount' => 80, 'issue_date' => '2026-10-20'];

        expect(proposalOf($bill, ['document' => ['category_id' => $deeds->id]])->warnings)->toBe([])
            ->and(proposalOf([...$bill, 'plate_number' => 'GH482TK'], ['document' => ['category_id' => $this->light->id]])->warnings)->toBe([]);
    });

    test('an expense that pays a planned deadline keeps the category of the planned expense', function (): void {
        $utility = Utility::factory()->for($this->family)->create(['supply_point_code' => 'IT001E00000001', 'person_id' => null]);
        $planned = ExpenseCategory::factory()->for($this->family)->create(['name' => 'Luce di casa']);
        PlannedExpense::factory()->for($this->family)->forCard($utility)->create(['title' => 'Luce', 'amount' => 80, 'category_id' => $planned->id, 'start_date' => '2026-01-15']);
        ExpenseCategory::factory()->for($this->family)->create(['name' => 'Bollette e utenze', 'card_type' => 'utility']);

        $proposal = proposalOf(
            ['kind' => 'utility_bill', 'supply_point_code' => 'IT001E00000001', 'total_amount' => 84.2, 'due_date' => '2026-10-15'],
            ['document' => ['category_id' => $this->light->id]],
        );

        expect($proposal->movement->values->category_id)->toBe($planned->id);
    });

    test('the expense category written by the person wins over the one of the card', function (): void {
        ExpenseCategory::factory()->for($this->family)->create(['name' => 'Bollette e utenze', 'card_type' => 'utility']);

        $proposal = proposalOf($this->bill, ['document' => ['category_id' => $this->light->id], 'overrides' => ['movement' => ['category_id' => $this->health->id]]]);

        expect($proposal->movement->values->category_id)->toBe($this->health->id);
    });

    test('a person whose only job has ended gets the box to create the job', function (): void {
        $marco = Person::factory()->for($this->family)->create(['first_name' => 'Marco']);
        Employment::factory()->for($this->family)->create(['person_id' => $marco->id, 'start_date' => '2020-01-01', 'end_date' => '2025-12-31']);
        $payslips = DocumentCategory::factory()->for($this->family)->create(['name' => 'Buste paga', 'card_type' => 'employment']);

        $proposal = proposalOf(['kind' => 'payslip', 'total_amount' => 1800, 'issue_date' => '2026-10-27'], [
            'document' => ['category_id' => $payslips->id, 'person_id' => $marco->id],
        ]);

        expect($proposal->records)->toHaveCount(1)
            ->and($proposal->records[0]->cardType)->toBe('employment');
    });
});

describe('the choices of the box of the card of the category', function (): void {
    beforeEach(function (): void {
        $this->deeds = DocumentCategory::factory()->for($this->family)->create(['name' => 'Rogiti', 'card_type' => 'home']);
        $this->deed = ['kind' => 'other', 'issue_date' => '2026-10-01'];
    });

    test('the fields written in the box of a home reach its values, and a field the home has not is dropped', function (): void {
        $proposal = proposalOf($this->deed, [
            'document' => ['category_id' => $this->deeds->id],
            'overrides' => ['records' => ['card' => ['name' => ' Casa al mare ', 'plate_number' => 'GH482TK']]],
        ]);

        $card = $proposal->records[0];
        expect($card->kind)->toBe(RecordKind::Card)
            ->and($card->values['name'])->toBe('Casa al mare')
            ->and($card->values)->not->toHaveKey('plate_number')
            ->and($card->missing)->toBe([])
            ->and($card->fields)->toBe([]);
    });

    test('a home picked by hand is linked', function (): void {
        $home = Home::factory()->for($this->family)->create(['name' => 'Casa di Milano']);

        $card = proposalOf($this->deed, [
            'document' => ['category_id' => $this->deeds->id],
            'targets' => ['records' => ['card' => $home->id]],
        ])->records[0];

        expect($card->existing)->toBe(['id' => $home->id, 'title' => 'Casa di Milano'])
            ->and($card->missing)->toBe([]);
    });

    test('the id of a vehicle, of a deleted home or of a home of another family links nothing', function (): void {
        $vehicle = Vehicle::factory()->for($this->family)->create();
        $deleted = Home::factory()->for($this->family)->create();
        $deleted->delete();
        $theirs = Home::factory()->for(Family::factory())->create();

        foreach ([$vehicle->id, $deleted->id, $theirs->id] as $id) {
            expect(Home::query()->find($id))->toBeNull()
                ->and(proposalOf($this->deed, [
                    'document' => ['category_id' => $this->deeds->id],
                    'targets' => ['records' => ['card' => $id]],
                ])->records[0]->existing)->toBeNull();
        }
    });

    test('"Choose another one" lists the cards of the type, by title', function (): void {
        $villa = Home::factory()->for($this->family)->create(['name' => 'Villa al lago']);
        $flat = Home::factory()->for($this->family)->create(['name' => 'Appartamento Milano']);
        Vehicle::factory()->for($this->family)->create(['name' => 'Panda']);

        $card = proposalOf($this->deed, ['document' => ['category_id' => $this->deeds->id]])->records[0];

        expect($card->candidates)->toBe([
            ['id' => $flat->id, 'label' => 'Appartamento Milano'],
            ['id' => $villa->id, 'label' => 'Villa al lago'],
        ]);
    });
});
