<?php

declare(strict_types=1);

use App\Data\Reading\EventValuesData;
use App\Data\Reading\Extraction;
use App\Data\Reading\MovementValuesData;
use App\Enums\EmploymentStatus;
use App\Enums\IncomeType;
use App\Models\Employment;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\InsurancePolicy;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\Utility;
use App\Services\Reading\MovementDraft;
use App\Services\Reading\ReadingContext;
use App\Support\CurrentFamily;

/**
 * The expense, income and event a reading proposes, with whether each starts ticked.
 *
 * @param  array<string, mixed>  $fields  the fields of the reading
 * @param  string  $locale  the language of the titles
 * @return array{movement: ?MovementValuesData, movementSuggested: bool, event: ?EventValuesData, eventSuggested: bool}
 */
function draft(array $fields, ReadingContext $context, string $locale = 'it'): array
{
    return resolve(MovementDraft::class)->of(new Extraction(...['confidence' => 'high', ...$fields]), $context, $locale);
}

beforeEach(function (): void {
    $this->family = Family::factory()->create(['name' => 'Bianchi']);
    CurrentFamily::set($this->family->id);
    $this->organization = Organization::factory()->for($this->family)->create(['name' => 'Energia Verde']);
    $this->anna = Person::factory()->for($this->family)->create(['first_name' => 'Anna', 'last_name' => 'Rossi', 'tax_code' => 'RSSNNA80A41H501X']);
    $this->category = ExpenseCategory::factory()->for($this->family)->create();
    $this->context = new ReadingContext(
        [['id' => $this->category->id, 'label' => 'Casa']],
        [],
        [['id' => $this->organization->id, 'label' => 'Energia Verde']],
        [['id' => $this->anna->id, 'label' => 'Anna Rossi']],
        null,
        null,
        $this->family->id,
    );
});

afterEach(fn () => CurrentFamily::clear());

describe('the expense', function (): void {
    test('expense: a bill finds the utility and the planned expense, and compares the amounts', function (): void {
        $utility = Utility::factory()->for($this->family)->create(['supply_point_code' => 'IT001E00000001', 'organization_id' => $this->organization->id, 'person_id' => null]);
        $planned = PlannedExpense::factory()->for($this->family)->forCard($utility)->create([
            'title' => 'Luce', 'amount' => 80, 'is_estimated' => true, 'category_id' => $this->category->id,
            'organization_id' => $this->organization->id, 'start_date' => '2026-01-15',
        ]);

        $out = draft([
            'kind' => 'utility_bill', 'supply_point_code' => 'it 001 e 00000001', 'total_amount' => 84.3,
            'due_date' => '2026-03-15', 'organization_id' => $this->organization->id,
        ], $this->context);

        expect($out['movement']->kind)->toBe('expense')
            ->and($out['movement']->title)->toBe('Luce marzo 2026')
            ->and($out['movement']->amount)->toBe(84.3)
            ->and($out['movement']->date)->toBe('2026-03-15')
            ->and($out['movement']->organization_id)->toBe($this->organization->id)
            ->and($out['movement']->category_id)->toBe($this->category->id)
            ->and($out['movement']->lead)->toEqual(['id' => $planned->id, 'title' => 'Luce', 'amount' => 80.0, 'is_estimated' => true])
            ->and($out['movementSuggested'])->toBeTrue();
    });

    test('expense: the holder is the one of the planned expense, otherwise the person of the paper', function (): void {
        $utility = Utility::factory()->for($this->family)->create(['supply_point_code' => 'IT001E00000001', 'person_id' => $this->anna->id]);
        $x = ['kind' => 'utility_bill', 'supply_point_code' => 'IT001E00000001', 'total_amount' => 84.3, 'due_date' => '2026-03-15'];

        expect(draft($x, $this->context)['movement']->person_id)->toBe($this->anna->id);

        $bruno = Person::factory()->for($this->family)->create();
        PlannedExpense::factory()->for($this->family)->forCard($utility)->create(['person_id' => $bruno->id, 'start_date' => '2026-01-15']);

        expect(draft($x, $this->context)['movement']->person_id)->toBe($bruno->id);
    });

    test('a payment receipt adds its fee to the amount', function (): void {
        $out = draft(['kind' => 'payment_receipt', 'total_amount' => 100.1, 'fee_amount' => 0.2, 'issue_date' => '2026-03-10', 'due_date' => '2026-03-20'], $this->context);

        expect($out['movement']->amount)->toBe(100.3)
            ->and($out['movement']->fee_amount)->toBe(0.2)
            ->and($out['movement']->date)->toBe('2026-03-10');
    });

    test('a bill is dated on its deadline, a receipt on the day of the payment', function (): void {
        $x = ['total_amount' => 10, 'issue_date' => '2026-03-01', 'due_date' => '2026-03-20'];

        expect(draft(['kind' => 'invoice', ...$x], $this->context)['movement']->date)->toBe('2026-03-20')
            ->and(draft(['kind' => 'invoice', ...$x, 'due_date' => null], $this->context)['movement']->date)->toBe('2026-03-01');
    });

    test('the account is the one of the IBAN, then the commitment, then the default', function (): void {
        $default = FinancialAccount::factory()->for($this->family)->create(['is_default' => true]);
        $iban = FinancialAccount::factory()->for($this->family)->create(['last_four_digits' => '1234', 'is_default' => false]);
        $x = ['kind' => 'invoice', 'total_amount' => 10, 'due_date' => '2026-03-20'];

        expect(draft($x, $this->context)['movement']->financial_account_id)->toBe($default->id)
            ->and(draft([...$x, 'iban_last_four' => '1234'], $this->context)['movement']->financial_account_id)->toBe($iban->id);
    });

    test('without a commitment the title is the one read, otherwise the issuer', function (): void {
        expect(draft(['kind' => 'invoice', 'title' => 'Tagliando', 'issue_date' => '2026-03-01', 'organization_id' => $this->organization->id], $this->context)['movement']->title)->toBe('Tagliando')
            ->and(draft(['kind' => 'invoice', 'organization_id' => $this->organization->id], $this->context)['movement']->title)->toBe('Energia Verde');
    });

    test('a period goes in the notes with the summary', function (): void {
        $out = draft(['kind' => 'invoice', 'summary' => 'Gas', 'period_start' => '2026-01-01', 'period_end' => '2026-02-28'], $this->context);

        expect($out['movement']->notes)->toBe("Gas\nPeriodo: 01/01/2026 – 28/02/2026");
    });

    test('an insurance without a commitment is not ticked', function (): void {
        expect(draft(['kind' => 'insurance', 'total_amount' => 300, 'policy_number' => 'P-1'], $this->context)['movementSuggested'])->toBeFalse();

        $policy = InsurancePolicy::factory()->for($this->family)->create(['policy_number' => 'P-1']);
        PlannedExpense::factory()->for($this->family)->forCard($policy)->create(['start_date' => '2026-03-01']);

        expect(draft(['kind' => 'insurance', 'total_amount' => 300, 'policy_number' => 'P-1', 'due_date' => '2026-03-01'], $this->context)['movementSuggested'])->toBeTrue();
    });

    test('a medical paper without an amount has no ticked expense', function (): void {
        expect(draft(['kind' => 'medical', 'health_type' => 'visit'], $this->context)['movementSuggested'])->toBeFalse()
            ->and(draft(['kind' => 'medical', 'health_type' => 'visit', 'total_amount' => 50], $this->context)['movementSuggested'])->toBeTrue();
    });
});

describe('the income', function (): void {
    test('income: a payslip finds the person by tax code and gives the net amount', function (): void {
        $out = draft([
            'kind' => 'payslip', 'tax_code' => 'rssnna80a41h501x', 'total_amount' => 1500.5, 'issue_date' => '2026-03-27',
            'period_start' => '2026-03-01', 'period_end' => '2026-03-31', 'issuer_name' => 'Acme Srl',
        ], $this->context);

        expect($out['movement']->kind)->toBe('income')
            ->and($out['movement']->person_id)->toBe($this->anna->id)
            ->and($out['movement']->amount)->toBe(1500.5)
            ->and($out['movement']->income_type)->toBe('salary')
            ->and($out['movement']->date)->toBe('2026-03-27')
            ->and($out['movementSuggested'])->toBeTrue();
    });

    test('income: the holder is the one of the planned income, otherwise the person of the employment', function (): void {
        $employment = Employment::factory()->for($this->family)->create([
            'person_id' => $this->anna->id, 'organization_id' => $this->organization->id, 'status' => EmploymentStatus::Active, 'start_date' => '2020-01-01', 'end_date' => null,
        ]);
        $x = ['kind' => 'payslip', 'total_amount' => 1500, 'issue_date' => '2026-03-27', 'period_start' => '2026-03-01', 'organization_id' => $this->organization->id];

        $byEmployment = draft($x, $this->context)['movement'];
        expect($byEmployment->person_id)->toBe($this->anna->id)->and($byEmployment->employment_id)->toBe($employment->id)->and($byEmployment->lead)->toBeNull();

        $bruno = Person::factory()->for($this->family)->create();
        $planned = PlannedIncome::factory()->for($this->family)->create([
            'title' => 'Stipendio', 'amount' => 1500, 'type' => IncomeType::Salary, 'employment_id' => $employment->id, 'person_id' => $bruno->id, 'start_date' => '2026-01-27',
        ]);

        $withPlanned = draft($x, $this->context)['movement'];
        expect($withPlanned->person_id)->toBe($bruno->id)
            ->and($withPlanned->title)->toBe('Stipendio marzo 2026')
            ->and($withPlanned->lead['id'])->toBe($planned->id);
    });
});

describe('the event', function (): void {
    test('health: a report finds the patient by tax code and the type visit', function (): void {
        $out = draft(['kind' => 'medical', 'tax_code' => 'RSSNNA80A41H501X', 'health_type' => 'visit', 'issue_date' => '2026-03-15', 'outcome' => 'Tutto nella norma.', 'next_checkup_date' => '2026-09-15'], $this->context);

        expect($out['event']->person_id)->toBe($this->anna->id)
            ->and($out['event']->type)->toBe('visit')
            ->and($out['event']->title)->toBe('Visita')
            ->and($out['event']->date)->toBe('2026-03-15')
            ->and($out['event']->next_checkup_date)->toBe('2026-09-15')
            ->and($out['event']->outcome)->toBe('Tutto nella norma.')
            ->and($out['eventSuggested'])->toBeTrue();
    });

    test('a prescription is not ticked', function (): void {
        $out = draft(['kind' => 'medical', 'health_type' => 'prescription'], $this->context);

        expect($out['event']->title)->toBe('Ricetta')
            ->and($out['eventSuggested'])->toBeFalse();
    });

    test('the event of a paper that is not medical exists only with a health type, and is named in English', function (): void {
        expect(draft(['kind' => 'invoice'], $this->context)['event'])->toBeNull()
            ->and(draft(['kind' => 'invoice', 'health_type' => 'optical'], $this->context, 'en')['event']->title)->toBe('Eyecare')
            ->and(draft(['kind' => 'medical'], $this->context)['event']->type)->toBeNull();
    });
});
