<?php

declare(strict_types=1);

use App\Enums\BankFilterAction;
use App\Enums\BankTransactionStatus;
use App\Enums\Role;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Services\Banks\BankGroups;
use App\Services\Banks\FakeBankFeed;
use App\Support\Banks\BankFilterDraft;
use App\Support\Banks\BankGroup;
use App\Support\Banks\BankText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-27 10:00', 'Europe/Rome'));
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
    $this->account = FinancialAccount::factory()->create(['family_id' => $this->marco->family_id]);
});

/**
 * A booked transaction of the family of Marco, on the first account unless the state says otherwise.
 *
 * @param  array<string, mixed>  $state  what to change in the transaction
 */
function bankGroupsRow(string $text, string $amount = '-10.00', string $day = '2026-11-20', array $state = []): BankTransaction
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

const BANK_GROUPS_SATISPAY = 'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A. CID.LU08HOO0000000000000001642 MAN.SAT0023101207360112900';

it('eleven direct debits of one mandate are one group, their commissions are not', function (): void {
    foreach (range(1, 11) as $i) {
        $day = CarbonImmutable::parse('2026-11-25')->subDays($i * 2);
        bankGroupsRow(BANK_GROUPS_SATISPAY, '-'.(10 + $i).'.00', $day->toDateString(), ['counterparty' => 'Satispay Europe S.A.']);
        bankGroupsRow('COMMISSIONI '.BANK_GROUPS_SATISPAY, '-0.40', $day->addDay()->toDateString(), ['counterparty' => 'Satispay Europe S.A.']);
    }

    $groups = resolve(BankGroups::class)->all();

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->name)->toBe('Satispay Europe S.A.')
        ->and($groups[0]->kind)->toBe('expense')
        ->and($groups[0]->accountId)->toBe($this->account->id)
        ->and($groups[0]->transactions)->toHaveCount(11)
        ->and($groups[0]->transactions->every(fn (BankTransaction $row): bool => ! str_starts_with($row->text, 'COMMISSIONI')))->toBeTrue()
        ->and($groups[0]->key)->toHaveLength(16)
        ->and(resolve(BankGroups::class)->find($groups[0]->key)?->key)->toBe($groups[0]->key);
});

it('the same shop on two accounts makes two groups', function (): void {
    $other = FinancialAccount::factory()->create(['family_id' => $this->marco->family_id]);

    foreach ([$this->account->id, $other->id] as $account) {
        bankGroupsRow('PAGAMENTO POS ENI STATION', '-30.00', '2026-11-10', ['financial_account_id' => $account, 'counterparty' => 'ENI']);
        bankGroupsRow('PAGAMENTO POS ENI STATION', '-31.00', '2026-11-12', ['financial_account_id' => $account, 'counterparty' => 'ENI']);
    }

    $groups = resolve(BankGroups::class)->all();

    expect($groups)->toHaveCount(2)
        ->and($groups[0]->key)->not->toBe($groups[1]->key)
        ->and(collect($groups)->pluck('accountId')->sort()->values()->all())->toBe(collect([$this->account->id, $other->id])->sort()->values()->all());
});

it('the same text paid and received makes two groups', function (): void {
    bankGroupsRow('RIMBORSO AMAZON', '-5.00', '2026-11-10');
    bankGroupsRow('RIMBORSO AMAZON', '-6.00', '2026-11-11');
    bankGroupsRow('RIMBORSO AMAZON', '5.00', '2026-11-12');
    bankGroupsRow('RIMBORSO AMAZON', '6.00', '2026-11-13');

    expect(collect(resolve(BankGroups::class)->all())->pluck('kind')->sort()->values()->all())->toBe(['expense', 'income']);
});

it('a single movement is lone', function (): void {
    $single = bankGroupsRow('PRELIEVO BANCOMAT', '-100.00');

    $service = resolve(BankGroups::class);

    expect($service->all())->toBe([])
        ->and($service->lone()->pluck('id')->all())->toBe([$single->id]);
});

it('lone leaves out the movements of a group and the commission of a debit, and keeps an orphan commission', function (): void {
    bankGroupsRow(BANK_GROUPS_SATISPAY, '-10.00', '2026-11-20');
    bankGroupsRow(BANK_GROUPS_SATISPAY, '-10.00', '2026-10-20');
    $paired = bankGroupsRow('COMMISSIONI '.BANK_GROUPS_SATISPAY, '-0.40', '2026-11-21');
    $orphan = bankGroupsRow('COMMISSIONI DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Gym Club S.r.l. CID.IT12ZZZ0000012345678901 MAN.GYM000456', '-0.40', '2026-11-12');
    $single = bankGroupsRow('PRELIEVO BANCOMAT', '-100.00');

    $lone = resolve(BankGroups::class)->lone()->pluck('id')->all();

    expect($lone)->toContain($single->id, $orphan->id)->not->toContain($paired->id)->toHaveCount(2);
});

it('what a filter takes is in no group', function (): void {
    bankGroupsRow('PAGAMENTO POS ENI STATION', '-30.00', '2026-11-10');
    bankGroupsRow('PAGAMENTO POS ENI STATION', '-31.00', '2026-11-12');
    expect(resolve(BankGroups::class)->all())->toHaveCount(1);

    BankFilter::factory()->create(['family_id' => $this->marco->family_id, 'contains' => ['eni station']]);

    expect(resolve(BankGroups::class)->all())->toBe([]);
});

it('a movement taken out of a group stays out', function (): void {
    $first = bankGroupsRow('PAGAMENTO POS ENI STATION', '-30.00', '2026-11-10');
    bankGroupsRow('PAGAMENTO POS ENI STATION', '-31.00', '2026-11-12');
    bankGroupsRow('PAGAMENTO POS ENI STATION', '-32.00', '2026-11-14');
    $service = resolve(BankGroups::class);
    $key = $service->keyOf($first);

    $first->forceFill(['rejected_proposals' => ['group:'.$key]])->save();
    $groups = resolve(BankGroups::class)->all();

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->transactions)->toHaveCount(2)
        ->and($groups[0]->transactions->pluck('id')->all())->not->toContain($first->id)
        ->and(resolve(BankGroups::class)->lone()->pluck('id')->all())->toBe([$first->id]);

    // The same, when the key is the key of the array
    $first->forceFill(['rejected_proposals' => ['group:'.$key => true]])->save();

    expect(resolve(BankGroups::class)->all()[0]->transactions)->toHaveCount(2);
});

it('groups come biggest first', function (): void {
    foreach ([1, 2] as $i) {
        bankGroupsRow('ADDEBITO SDD EDISON ENERGIA', '-5'.$i.'.00', '2026-11-1'.$i);
    }
    foreach ([1, 2, 3] as $i) {
        bankGroupsRow('PAGAMENTO POS ENI STATION', '-3'.$i.'.00', '2026-11-1'.$i);
    }

    $groups = resolve(BankGroups::class)->all();

    expect($groups)->toHaveCount(2)
        ->and($groups[0]->transactions)->toHaveCount(3)
        ->and($groups[1]->transactions)->toHaveCount(2);
});

it('gives the others of the group of a movement, even when it is sorted already', function (): void {
    $rows = collect([10, 11, 12])->map(fn (int $day): BankTransaction => bankGroupsRow('PAGAMENTO POS ENI STATION', '-30.00', '2026-11-'.$day));
    $rows[0]->forceFill(['ignored_at' => now()])->save();

    $siblings = resolve(BankGroups::class)->siblings($rows[0]);

    expect($siblings->pluck('id')->sort()->values()->all())->toBe([$rows[1]->id, $rows[2]->id]);
});

it('never asks the database once for each transaction', function (): void {
    foreach (range(1, 50) as $i) {
        bankGroupsRow($i % 2 === 0 ? 'PAGAMENTO POS ENI STATION' : BANK_GROUPS_SATISPAY, '-'.(10 + $i).'.00', CarbonImmutable::parse('2026-11-20')->subDays($i)->toDateString());
    }
    bankGroupsRow('COMMISSIONI '.BANK_GROUPS_SATISPAY, '-0.40', '2026-11-19');
    BankFilter::factory()->create(['family_id' => $this->marco->family_id, 'contains' => ['nothing here']]);
    $service = resolve(BankGroups::class);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $groups = $service->all();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($groups)->toHaveCount(2)->and($queries)->toBeLessThanOrEqual(4);
});

it('finds the groups and the lone movements of the fake bank', function (): void {
    $this->account->forceFill(['bank_connection_id' => null])->save();
    $feed = new FakeBankFeed;
    $today = CarbonImmutable::now('Europe/Rome')->startOfDay();

    foreach ($feed->transactions('s', 'u', $today->subDays(90), $today) as $entry) {
        BankTransaction::factory()->create([
            'family_id' => $this->marco->family_id,
            'financial_account_id' => $this->account->id,
            'provider_id' => $entry->providerId,
            'booked_on' => $entry->bookedOn,
            'value_on' => $entry->valueOn,
            'amount' => $entry->amount,
            'text' => $entry->text,
            'counterparty' => $entry->counterparty,
            'status' => $entry->booked ? BankTransactionStatus::Booked : BankTransactionStatus::Pending,
        ]);
    }
    $service = resolve(BankGroups::class);
    $names = collect($service->all())->map(fn (BankGroup $group): string => mb_strtolower($group->name))->all();

    expect(collect($names)->contains(fn (string $name): bool => str_contains($name, 'satispay')))->toBeTrue()
        ->and($names)->toContain('inps', 'eni')
        ->and(collect($names)->contains(fn (string $name): bool => str_contains($name, 'cashback')))->toBeTrue()
        ->and($service->lone()->count())->toBeGreaterThanOrEqual(8);
});

/**
 * Turns the link to the bank on for the family of Marco, with the fake bank, and signs in as him or as a member.
 */
function bankGroupsSimilarOn(bool $member = false): User
{
    config(['bank_feed.provider' => 'fake']);
    test()->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $user = $member ? User::factory()->create(['family_id' => test()->marco->family_id, 'role' => Role::Member]) : test()->marco;
    test()->actingAs($user);

    return $user;
}

/**
 * The body of a new expense made from a bank transaction, as the form sends it.
 */
function bankGroupsExpenseBody(BankTransaction $row): array
{
    return [
        'kind' => 'expense', 'title' => 'Bar Rossi', 'amount' => 10, 'date' => '2026-11-05',
        'financial_account_id' => $row->financial_account_id, 'bank_transaction_id' => $row->id,
    ];
}

it('after creating an expense from a movement of a group the page says how many others are like it', function (): void {
    bankGroupsSimilarOn();
    $rows = collect(range(1, 8))->map(fn (int $day): BankTransaction => bankGroupsRow('PAGAMENTO POS BAR ROSSI', '-10.00', '2026-11-0'.$day, ['counterparty' => 'Bar Rossi']));

    $this->post(route('budget.movements.store'), bankGroupsExpenseBody($rows[0]))
        ->assertRedirect(route('budget.bank.index'))
        ->assertInertiaFlash('similar.count', 7)
        ->assertInertiaFlash('similar.action', 'create')
        ->assertInertiaFlash('similar.url', route('budget.bank.filters.create', ['from' => $rows[0]->id], absolute: false));

    $flash = session('inertia.flash_data.similar');

    expect($flash['rows'])->toHaveCount(7)
        ->and(collect($flash['rows'])->pluck('id')->all())->each->not->toBe($rows[0]->id);
});

it('says nothing for a lone movement', function (): void {
    bankGroupsSimilarOn();
    $row = bankGroupsRow('PAGAMENTO POS BAR ROSSI', '-10.00', '2026-11-01', ['counterparty' => 'Bar Rossi']);

    $this->post(route('budget.movements.store'), bankGroupsExpenseBody($row))
        ->assertRedirect(route('budget.bank.index'))
        ->assertInertiaFlashMissing('similar');
});

it('says the same after the account of a transfer is chosen', function (): void {
    bankGroupsSimilarOn();
    $savings = FinancialAccount::factory()->create(['family_id' => $this->marco->family_id]);
    $rows = collect([1, 2, 3])->map(fn (int $day): BankTransaction => bankGroupsRow('GIROCONTO RISPARMIO', '-100.00', '2026-11-0'.$day));

    $this->post(route('budget.bank.transfer', $rows[0]), ['account_id' => $savings->id])
        ->assertRedirect(route('budget.bank.index'))
        ->assertInertiaFlash('similar.count', 2)
        ->assertInertiaFlash('similar.action', 'transfer');
});

it('the draft carries the fields of the expense just created', function (): void {
    bankGroupsSimilarOn();
    $category = ExpenseCategory::factory()->create(['family_id' => $this->marco->family_id]);
    $organization = Organization::factory()->create(['family_id' => $this->marco->family_id]);
    $person = Person::factory()->create(['family_id' => $this->marco->family_id]);
    $row = bankGroupsRow('PAGAMENTO POS BAR ROSSI', '-10.00', '2026-11-01', ['counterparty' => 'Bar Rossi']);
    $expense = Expense::factory()->create([
        'family_id' => $this->marco->family_id, 'title' => 'Colazione', 'category_id' => $category->id,
        'organization_id' => $organization->id, 'person_id' => $person->id, 'planned_expense_id' => null,
    ]);
    $row->forceFill(['expense_id' => $expense->id])->save();

    $draft = BankFilterDraft::fromLink($row->refresh());

    expect($draft->action)->toBe(BankFilterAction::Create)
        ->and($draft->title)->toBe('Colazione')
        ->and($draft->titleWithMonth)->toBeFalse()
        ->and($draft->categoryId)->toBe($category->id)
        ->and($draft->organizationId)->toBe($organization->id)
        ->and($draft->personId)->toBe($person->id)
        ->and($draft->contains)->toContain('BAR', 'ROSSI')
        ->and($draft->excludes)->toBe(['COMMISSIONI'])
        ->and($draft->direction)->toBe('expense')
        ->and($draft->accountId)->toBe($this->account->id);
});

it('the draft carries the planned item just paid', function (): void {
    bankGroupsSimilarOn();
    $planned = PlannedExpense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Luce']);
    $row = bankGroupsRow('ADDEBITO DIRETTO EDISON', '-72.00', '2026-11-01');
    $expense = Expense::factory()->create(['family_id' => $this->marco->family_id, 'planned_expense_id' => $planned->id]);
    $row->forceFill(['expense_id' => $expense->id])->save();

    $draft = BankFilterDraft::fromLink($row->refresh());
    $member = BankFilterDraft::fromLink($row, mayPay: false);

    expect($draft->action)->toBe(BankFilterAction::Pay)
        ->and($draft->plannedExpenseId)->toBe($planned->id)
        ->and($draft->name)->toBe('Luce')
        ->and($member->action)->toBeNull()
        ->and($member->plannedExpenseId)->toBeNull();
});

it('the draft carries the account of the transfer', function (): void {
    $savings = FinancialAccount::factory()->create(['family_id' => $this->marco->family_id]);
    $row = bankGroupsRow('GIROCONTO RISPARMIO', '-100.00', '2026-11-01', ['transfer_account_id' => $savings->id]);

    $draft = BankFilterDraft::fromLink($row);

    expect($draft->action)->toBe(BankFilterAction::Transfer)
        ->and($draft->transferAccountId)->toBe($savings->id);
});

it('the new filter form from a movement uses the draft of the link, and a movement of another family is 404', function (): void {
    bankGroupsSimilarOn();
    $savings = FinancialAccount::factory()->create(['family_id' => $this->marco->family_id]);
    $row = bankGroupsRow('GIROCONTO RISPARMIO', '-100.00', '2026-11-01', ['transfer_account_id' => $savings->id]);
    $rossi = registerFamily('Rossi', 'rossi@example.com');
    $theirs = insideFamilyOf($rossi, fn (): BankTransaction => BankTransaction::factory()->create(['family_id' => $rossi->family_id]));
    $this->actingAs($this->marco);

    $this->get(route('budget.bank.filters.create', ['from' => $row->id]))
        ->assertInertia(fn ($page) => $page->where('draft.action', 'transfer')->where('draft.transfer_account_id', $savings->id));
    $this->get(route('budget.bank.filters.create', ['from' => $theirs->id]))->assertNotFound();
    $this->get(route('budget.bank.filters.create', ['from' => 999999]))->assertNotFound();
});

it('paying a due date with no filter leads to the new filter form and offers nothing more', function (): void {
    bankGroupsSimilarOn();
    $planned = PlannedExpense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Luce', 'start_date' => '2026-01-05', 'financial_account_id' => null, 'bank_match' => null]);
    $rows = collect([1, 2, 3])->map(fn (int $day): BankTransaction => bankGroupsRow('ADDEBITO DIRETTO EDISON', '-72.00', '2026-11-0'.$day, ['counterparty' => 'Edison']));

    $this->post(route('budget.bank.assign', $rows[0]), ['kind' => 'expense', 'planned_id' => $planned->id])
        ->assertRedirect(route('budget.bank.filters.create', ['planned' => 'expense:'.$planned->id, 'from' => $rows[0]->id]))
        ->assertInertiaFlashMissing('similar');
});

it('says what the filter for the others would do after a recorded expense is linked', function (): void {
    bankGroupsSimilarOn();
    $rows = collect([1, 2, 3])->map(fn (int $day): BankTransaction => bankGroupsRow('PAGAMENTO POS BAR ROSSI', '-10.00', '2026-11-0'.$day, ['counterparty' => 'Bar Rossi']));
    $expense = Expense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Bar Rossi', 'amount' => 10, 'date' => '2026-11-01']);

    $this->post(route('budget.bank.assign', $rows[0]), ['kind' => 'expense', 'movement_id' => $expense->id])
        ->assertRedirect(route('budget.bank.index'))
        ->assertInertiaFlash('similar.count', 2)
        ->assertInertiaFlash('similar.action', 'create');
});

it('offers the filter for the others after a due date is paid that already has a paying filter', function (): void {
    bankGroupsSimilarOn();
    $planned = PlannedExpense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Luce', 'start_date' => '2026-01-05', 'financial_account_id' => null, 'bank_match' => null]);
    BankFilter::factory()->pays($planned)->create(['family_id' => $this->marco->family_id, 'contains' => ['MUTUO']]);
    $rows = collect([1, 2, 3])->map(fn (int $day): BankTransaction => bankGroupsRow('ADDEBITO DIRETTO EDISON', '-72.00', '2026-11-0'.$day, ['counterparty' => 'Edison']));

    $this->post(route('budget.bank.assign', $rows[0]), ['kind' => 'expense', 'planned_id' => $planned->id])
        ->assertRedirect(route('budget.bank.index'))
        ->assertInertiaFlash('similar.count', 2)
        ->assertInertiaFlash('similar.action', 'pay');
});

it('offers nothing to a member who pays a due date, who cannot make a filter that pays', function (): void {
    bankGroupsSimilarOn(member: true);
    $planned = PlannedExpense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Luce', 'start_date' => '2026-01-05', 'financial_account_id' => null, 'bank_match' => null]);
    $rows = collect([1, 2, 3])->map(fn (int $day): BankTransaction => bankGroupsRow('ADDEBITO DIRETTO EDISON', '-72.00', '2026-11-0'.$day, ['counterparty' => 'Edison']));

    $this->post(route('budget.bank.assign', $rows[0]), ['kind' => 'expense', 'planned_id' => $planned->id])
        ->assertRedirect(route('budget.bank.index'))
        ->assertInertiaFlashMissing('similar');
});

it('the flash carries the filter as data, why they are alike, the amounts and the days', function (): void {
    bankGroupsSimilarOn();
    $rows = collect([['1', '-10.00'], ['2', '-30.00'], ['3', '-50.00']])->map(fn (array $day): BankTransaction => bankGroupsRow('ADDEBITO SDD SATISPAY EUROPE MAN.ABC123 CID.IT99', $day[1], '2026-11-0'.$day[0], ['counterparty' => 'Satispay']));

    $this->post(route('budget.movements.store'), bankGroupsExpenseBody($rows[0]))
        ->assertInertiaFlash('similar.filter.action', 'create')
        ->assertInertiaFlash('similar.filter.title', 'Bar Rossi')
        ->assertInertiaFlash('similar.filter.fee_mode', 'auto')
        ->assertInertiaFlash('similar.reason.kind', 'mandate')
        ->assertInertiaFlash('similar.kind', 'expense')
        ->assertInertiaFlash('similar.min', 30)
        ->assertInertiaFlash('similar.max', 50)
        ->assertInertiaFlash('similar.from', '2026-11-02')
        ->assertInertiaFlash('similar.to', '2026-11-03');
});

it('says the words when there is no mandate', function (): void {
    bankGroupsSimilarOn();
    $rows = collect([1, 2, 3])->map(fn (int $day): BankTransaction => bankGroupsRow('PAGAMENTO POS BAR ROSSI', '-10.00', '2026-11-0'.$day, ['counterparty' => 'Bar Rossi']));

    $this->post(route('budget.movements.store'), bankGroupsExpenseBody($rows[0]))
        ->assertInertiaFlash('similar.reason.kind', 'words');
});

it('carries at most twenty rows and the real count when there are more than twenty others', function (): void {
    bankGroupsSimilarOn();
    $rows = collect(range(1, 24))->map(fn (int $day): BankTransaction => bankGroupsRow('PAGAMENTO POS BAR ROSSI', '-10.00', '2026-10-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT), ['counterparty' => 'Bar Rossi']));

    $this->post(route('budget.movements.store'), bankGroupsExpenseBody($rows[0]))
        ->assertInertiaFlash('similar.count', 23);

    expect(session('inertia.flash_data.similar')['rows'])->toHaveCount(20);
});

it('the draft of a planned item has no excluded words without a movement, and the words of the movement with one', function (): void {
    $planned = PlannedExpense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Luce']);
    $row = bankGroupsRow('ADDEBITO DIRETTO EDISON ENERGIA', '-72.00', '2026-11-01');

    $alone = BankFilterDraft::fromPlanned($planned);
    $with = BankFilterDraft::fromPlanned($planned, $row);

    expect($alone->excludes)->toBe([])->and($alone->contains)->toBe([])->and($alone->name)->toBe('Luce')
        ->and($alone->action)->toBe(BankFilterAction::Pay)->and($alone->plannedExpenseId)->toBe($planned->id)
        ->and($with->excludes)->toBe(['COMMISSIONI'])->and($with->contains)->toBe(BankText::words($row->text))
        ->and($with->accountId)->toBe($row->financial_account_id);
});
