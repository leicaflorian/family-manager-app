<?php

declare(strict_types=1);

use App\Data\BankGroupData;
use App\Enums\BankFilterAction;
use App\Enums\BankFilterFee;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\Income;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Banks\BankGroupProposals;
use App\Services\Banks\BankGroups;
use App\Services\Banks\RecurringBankTexts;
use App\Support\Banks\BankFilterDraft;
use App\Support\Banks\BankGroup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-27 10:00', 'Europe/Rome'));
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
    $this->account = FinancialAccount::factory()->create(['family_id' => $this->marco->family_id]);
});

/**
 * A booked transaction of the family of Marco.
 *
 * @param  array<string, mixed>  $state  what to change in the transaction
 */
function bankGroupProposalsRow(string $text, string $amount, string $day, array $state = []): BankTransaction
{
    return BankTransaction::factory()->create([
        'family_id' => test()->marco->family_id,
        'financial_account_id' => test()->account->id,
        'text' => $text,
        'counterparty' => null,
        'booked_on' => $day,
        'value_on' => $day,
        'amount' => $amount,
        ...$state,
    ]);
}

/** The only group of the family. */
function bankGroupProposalsGroup(): BankGroup
{
    $groups = resolve(BankGroups::class)->all();
    expect($groups)->toHaveCount(1);

    return $groups[0];
}

const BANK_GROUP_PROPOSALS_SATISPAY = 'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A. CID.LU08HOO0000000000000001642 MAN.SAT0023101207360112900';

it('proposes to pay the planned income whose organization is in the text', function (): void {
    $inps = Organization::factory()->create(['family_id' => $this->marco->family_id, 'name' => 'INPS']);
    $planned = PlannedIncome::factory()->create(['family_id' => $this->marco->family_id, 'organization_id' => $inps->id, 'amount' => 300, 'end_date' => null]);
    foreach (['2026-09-20', '2026-10-20', '2026-11-20'] as $day) {
        bankGroupProposalsRow('ACCREDITO INPS ASSEGNO UNICO', '104.50', $day);
    }

    $proposal = resolve(BankGroupProposals::class)->for(bankGroupProposalsGroup());

    expect($proposal)->toBeInstanceOf(BankFilterDraft::class)
        ->and($proposal->action)->toBe(BankFilterAction::Pay)
        ->and($proposal->plannedIncomeId)->toBe($planned->id)
        ->and($proposal->plannedExpenseId)->toBeNull();
});

it('proposes to pay the planned expense that has the amount of every movement', function (): void {
    $planned = PlannedExpense::factory()->create(['family_id' => $this->marco->family_id, 'amount' => 49.90]);
    foreach (['2026-09-20', '2026-10-20'] as $day) {
        bankGroupProposalsRow('PAGAMENTO POS PALESTRA', '-49.90', $day);
    }

    expect(resolve(BankGroupProposals::class)->for(bankGroupProposalsGroup())?->plannedExpenseId)->toBe($planned->id);
});

it('does not propose a planned item that is finished or already has a filter', function (): void {
    $inps = Organization::factory()->create(['family_id' => $this->marco->family_id, 'name' => 'INPS']);
    PlannedIncome::factory()->create(['family_id' => $this->marco->family_id, 'organization_id' => $inps->id, 'end_date' => '2026-10-01']);
    $withFilter = PlannedIncome::factory()->create(['family_id' => $this->marco->family_id, 'organization_id' => $inps->id, 'end_date' => null]);
    BankFilter::factory()->pays($withFilter)->create(['family_id' => $this->marco->family_id, 'contains' => ['unrelated words']]);
    foreach (['2026-10-20', '2026-11-20'] as $day) {
        bankGroupProposalsRow('ACCREDITO INPS ASSEGNO UNICO', '104.50', $day);
    }

    expect(resolve(BankGroupProposals::class)->for(bankGroupProposalsGroup()))->toBeNull();
});

it('proposes a planned item that kept an old rule after its filter was deleted', function (): void {
    $planned = PlannedExpense::factory()->create(['family_id' => $this->marco->family_id, 'amount' => 49.90, 'bank_match' => 'PALESTRA']);
    foreach (['2026-09-20', '2026-10-20'] as $day) {
        bankGroupProposalsRow('PAGAMENTO POS PALESTRA', '-49.90', $day);
    }

    expect(resolve(BankGroupProposals::class)->for(bankGroupProposalsGroup())?->plannedExpenseId)->toBe($planned->id);
});

it('proposes to create the expense like the latest recorded one', function (): void {
    $satispay = Organization::factory()->create(['family_id' => $this->marco->family_id, 'name' => 'Satispay']);
    $category = ExpenseCategory::factory()->create(['family_id' => $this->marco->family_id, 'name' => 'Varie']);
    $person = Person::factory()->create(['family_id' => $this->marco->family_id]);
    Expense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Old title', 'organization_id' => $satispay->id, 'date' => '2026-05-02', 'amount' => 12]);
    Expense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Ricarica Satispay ottobre 2026', 'organization_id' => $satispay->id, 'category_id' => $category->id, 'person_id' => $person->id, 'date' => '2026-10-24', 'amount' => 20]);
    // More than a year old: out
    Expense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Ancient', 'organization_id' => $satispay->id, 'date' => '2025-09-24', 'amount' => 20]);
    foreach (['2026-09-24', '2026-10-24'] as $day) {
        bankGroupProposalsRow(BANK_GROUP_PROPOSALS_SATISPAY, '-10.00', $day, ['counterparty' => 'Satispay Europe S.A.']);
    }

    $proposal = resolve(BankGroupProposals::class)->for(bankGroupProposalsGroup());

    expect($proposal?->action)->toBe(BankFilterAction::Create)
        ->and($proposal->title)->toBe('Ricarica Satispay')
        ->and($proposal->titleWithMonth)->toBeTrue()
        ->and($proposal->categoryId)->toBe($category->id)
        ->and($proposal->organizationId)->toBe($satispay->id)
        ->and($proposal->personId)->toBe($person->id)
        ->and($proposal->detail)->toBe('Varie · Satispay');
});

it('proposes to create from an expense with the amount and the date of a movement', function (): void {
    Expense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Distributore', 'date' => '2026-10-22', 'amount' => 30]);
    Expense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Too far', 'date' => '2026-10-10', 'amount' => 31]);
    bankGroupProposalsRow('PAGAMENTO POS ENI STATION', '-30.00', '2026-10-25');
    bankGroupProposalsRow('PAGAMENTO POS ENI STATION', '-31.00', '2026-11-20');

    expect(resolve(BankGroupProposals::class)->for(bankGroupProposalsGroup())?->title)->toBe('Distributore');
});

it('nothing for a cashback nobody recorded', function (): void {
    Income::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Stipendio']);
    PlannedIncome::factory()->create(['family_id' => $this->marco->family_id, 'amount' => 999, 'end_date' => null]);
    foreach (['2026-09-20', '2026-10-20', '2026-11-20'] as $day) {
        bankGroupProposalsRow('ACCREDITO CASHBACK SCONTIPOSTE', '0.40', $day);
    }

    expect(resolve(BankGroupProposals::class)->for(bankGroupProposalsGroup()))->toBeNull();
});

it('offers It comes back every month for three months in a row with no planned item', function (): void {
    foreach (['2026-09-12', '2026-10-12', '2026-11-12'] as $day) {
        bankGroupProposalsRow('ADDEBITO DIRETTO SDD NETFLIX INTERNATIONAL BV', '-13.99', $day);
    }
    $group = bankGroupProposalsGroup();
    $proposal = resolve(BankGroupProposals::class)->for($group);

    expect($proposal)->toBeNull()
        ->and(resolve(RecurringBankTexts::class)->groupRecurs($group->transactions))->toBeTrue()
        ->and(BankGroupData::fromGroup($group, $proposal, '/budget/planned/expense/create?bank=1')->recurring_url)->toBe('/budget/planned/expense/create?bank=1');
});

it('does not offer it when two months are missing, or when the group has a proposal to pay', function (): void {
    foreach (['2026-09-12', '2026-11-12'] as $day) {
        bankGroupProposalsRow('ADDEBITO DIRETTO SDD NETFLIX INTERNATIONAL BV', '-13.99', $day);
    }

    expect(resolve(RecurringBankTexts::class)->groupRecurs(bankGroupProposalsGroup()->transactions))->toBeFalse();

    bankGroupProposalsRow('ADDEBITO DIRETTO SDD NETFLIX INTERNATIONAL BV', '-13.99', '2026-10-12');
    $planned = PlannedExpense::factory()->create(['family_id' => $this->marco->family_id, 'amount' => 13.99]);
    $group = bankGroupProposalsGroup();
    $pay = resolve(BankGroupProposals::class)->for($group);

    expect($pay?->action)->toBe(BankFilterAction::Pay)
        ->and($pay->plannedExpenseId)->toBe($planned->id)
        ->and(BankGroupData::fromGroup($group, $pay, '/somewhere')->recurring_url)->toBeNull();
});

it('writes a filter draft from a group: words of the mandate, no commissions, the direction and the account', function (): void {
    foreach (['2026-10-24', '2026-11-24'] as $day) {
        bankGroupProposalsRow(BANK_GROUP_PROPOSALS_SATISPAY, '-10.00', $day, ['counterparty' => 'Satispay Europe S.A.']);
    }
    $group = bankGroupProposalsGroup();

    $draft = BankFilterDraft::fromGroup($group, null);

    expect($draft->contains)->toBe(['MAN.SAT0023101207360112900'])
        ->and($draft->excludes)->toBe(['COMMISSIONI'])
        ->and($draft->direction)->toBe('expense')
        ->and($draft->accountId)->toBe($this->account->id)
        ->and($draft->feeMode)->toBe(BankFilterFee::Auto)
        ->and($draft->action)->toBeNull()
        ->and($draft->toArray())->toMatchArray(['contains' => ['MAN.SAT0023101207360112900'], 'fee_mode' => 'auto', 'financial_account_id' => $this->account->id, 'action' => null]);
});

it('keeps the words every movement of the group has', function (): void {
    foreach (['VIA EMILIA', 'LAVAGGIO SELF MODENA'] as $place) {
        bankGroupProposalsRow('PAGAMENTO POS ENI '.$place, '-30.00', '2026-11-10', ['counterparty' => 'ENI']);
    }

    expect(BankFilterDraft::fromGroup(bankGroupProposalsGroup(), null)->contains)->toBe(['ENI']);
});

it('takes the action of the proposal into the draft and the data of the group', function (): void {
    $planned = PlannedExpense::factory()->create(['family_id' => $this->marco->family_id, 'amount' => 49.90, 'title' => 'Palestra']);
    foreach (['2026-09-20', '2026-10-20'] as $day) {
        bankGroupProposalsRow('PAGAMENTO POS PALESTRA', '-49.90', $day);
    }
    $group = bankGroupProposalsGroup();
    $proposal = resolve(BankGroupProposals::class)->for($group);

    $draft = BankFilterDraft::fromGroup($group, $proposal);
    $data = BankGroupData::fromGroup($group, $proposal, null);

    expect($draft->action)->toBe(BankFilterAction::Pay)
        ->and($draft->plannedExpenseId)->toBe($planned->id)
        ->and($draft->contains)->toBe(['PALESTRA'])
        ->and($data->count)->toBe(2)
        ->and($data->min)->toBe(49.9)
        ->and($data->max)->toBe(49.9)
        ->and($data->from)->toBe('2026-09-20')
        ->and($data->to)->toBe('2026-10-20')
        ->and($data->kind)->toBe('expense')
        ->and($data->proposal_action)->toBe('pay')
        ->and($data->proposal_detail)->toBe('Palestra');
});

it('falls back to the words of the newest movement when the group shares none', function (): void {
    bankGroupProposalsRow('POS ALFA', '-10.00', '2026-11-10', ['counterparty' => 'AB']);
    bankGroupProposalsRow('POS BETA', '-11.00', '2026-11-12', ['counterparty' => 'AB']);
    $group = bankGroupProposalsGroup();

    $contains = BankFilterDraft::fromGroup($group, null)->contains;

    expect($contains)->not->toBe([])->and($contains)->toContain('BETA');
});

it('reads the same number of queries for ten groups as for one', function (): void {
    foreach (range(1, 10) as $i) {
        $name = 'SHOP'.chr(64 + $i).' STORE';
        Organization::factory()->create(['family_id' => $this->marco->family_id, 'name' => $name]);
        foreach ([10, 12] as $day) {
            bankGroupProposalsRow('PAGAMENTO POS '.$name, '-'.(20 + $i).'.00', '2026-11-'.$day);
        }
    }
    $groups = collect(resolve(BankGroups::class)->all());
    expect($groups)->toHaveCount(10);

    $count = function (Collection $some): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $proposals = resolve(BankGroupProposals::class)->forAll($some);
        $some->each(fn ($group): BankGroupData => BankGroupData::fromGroup($group, $proposals[$group->key], null));
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($count($groups))->toBe($count($groups->take(1)));
});
