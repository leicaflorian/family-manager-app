<?php

declare(strict_types=1);

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
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\File;
use App\Models\FinancialAccount;
use App\Models\HealthEvent;
use App\Models\InsurancePolicy;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\Utility;
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
        ]);

        $event = proposalOf($this->ferri)->event;

        expect($event->existing)->toBe(['id' => $visit->id, 'title' => 'Dentista di Giulia', 'date' => '2026-10-22'])
            ->and($event->reminder)->toBeNull()
            ->and(array_column($event->candidates, 'id'))->toBe([$visit->id])
            ->and(proposalOf([...$this->ferri, 'person_id' => null], ['targets' => ['event' => 'new']])->event->blocked)
            ->toBe("Per creare l'evento di salute manca la persona.");
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
