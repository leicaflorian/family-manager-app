<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Services\Banks\BankGroups;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['bank_feed.provider' => 'fake']);
    Date::setTestNow(CarbonImmutable::parse('2026-11-27 10:00', 'Europe/Rome'));
    $this->marco = registerFamily();
    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $this->actingAs($this->marco);
    $this->account = FinancialAccount::factory()->create(['family_id' => $this->marco->family_id]);
});

/**
 * A booked transaction on the account of the family of Marco.
 *
 * @param  array<string, mixed>  $state  what to change in the transaction
 */
function bankGroupPagesRow(string $text, string $amount, string $day, array $state = []): BankTransaction
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

/**
 * Two groups (three Netflix debits, two Eni payments) and two lone movements.
 */
function bankGroupPagesSeed(): void
{
    foreach (['2026-09-12', '2026-10-12', '2026-11-12'] as $day) {
        bankGroupPagesRow('ADDEBITO DIRETTO SDD NETFLIX INTERNATIONAL BV', '-13.99', $day, ['counterparty' => 'Netflix']);
    }
    foreach (['2026-10-25', '2026-11-20'] as $day) {
        bankGroupPagesRow('PAGAMENTO POS ENI STATION', '-30.00', $day, ['counterparty' => 'Eni Station']);
    }
    bankGroupPagesRow('BONIFICO A FRATELLO ROSSI', '-50.00', '2026-11-22');
    bankGroupPagesRow('PRELIEVO BANCOMAT VIA ROMA', '-100.00', '2026-11-23');
}

it('the to-sort tab carries the groups biggest first and the counts', function (): void {
    bankGroupPagesSeed();

    $this->get(route('budget.bank.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('show', 'unsorted')
        ->has('groups', 2)
        ->where('groups.0.count', 3)
        ->where('groups.0.name', 'Netflix')
        ->where('groups.0.kind', 'expense')
        ->where('groups.0.min', 13.99)
        ->where('groups.0.from', '2026-09-12')
        ->where('groups.0.to', '2026-11-12')
        ->where('groups.1.count', 2)
        ->has('rows', 2)
        ->where('counts', ['unsorted' => 7, 'groups' => 2, 'lone' => 2])
        ->missing('group_rows'));
});

it('does not list a group in the rows, and does not ask the groups for the other tabs', function (): void {
    bankGroupPagesSeed();

    $this->get(route('budget.bank.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('rows', fn ($rows): bool => collect($rows)->pluck('text')->sort()->values()->all() === ['BONIFICO A FRATELLO ROSSI', 'PRELIEVO BANCOMAT VIA ROMA']));
    $this->get(route('budget.bank.index', ['show' => 'all']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('groups', 0));
});

it('group_rows come only when asked and say what the proposal would do', function (): void {
    $eni = Organization::factory()->create(['family_id' => $this->marco->family_id, 'name' => 'Eni']);
    Expense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Distributore', 'organization_id' => $eni->id, 'date' => '2026-10-25', 'amount' => 30]);
    bankGroupPagesSeed();
    $key = collect(resolve(BankGroups::class)->all())->firstWhere('name', 'Eni Station')->key;

    $this->get(route('budget.bank.index', ['group' => $key]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->missing('group_rows')
        ->reloadOnly('group_rows', fn (AssertableInertia $reload): AssertableInertia => $reload
            ->has('group_rows', 2)
            ->where('group_rows.0.transaction.booked_on', '2026-11-20')
            ->where('group_rows.0.will', 'create')
            ->where('group_rows.1.transaction.booked_on', '2026-10-25')
            ->where('group_rows.1.will', 'link')
            ->where('group_rows.1.will_title', 'Distributore')
            ->missing('groups')));

    // Not asked: not on the first visit without a group, and an unknown group gives nothing
    $this->get(route('budget.bank.index', ['group' => 'nonexistent0000']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->reloadOnly('group_rows', fn (AssertableInertia $reload): AssertableInertia => $reload->has('group_rows', 0)));
});

it('a group that comes back every month with nothing to pay offers the planned form, one that pays does not', function (): void {
    bankGroupPagesSeed();
    $newest = BankTransaction::query()->where('text', 'like', '%NETFLIX%')->latest('booked_on')->firstOrFail();

    $this->get(route('budget.bank.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('groups.0.proposal_action', null)
        ->where('groups.0.recurring_url', route('budget.planned.create', ['kind' => 'expense', 'bank' => $newest->id], false))
        ->where('groups.1.recurring_url', null));

    PlannedExpense::factory()->create(['family_id' => $this->marco->family_id, 'amount' => 13.99]);

    $this->get(route('budget.bank.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('groups.0.proposal_action', 'pay')
        ->where('groups.0.recurring_url', null));
});

it('reads the groups with a fixed number of queries', function (): void {
    bankGroupPagesSeed();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->get(route('budget.bank.index'))->assertOk();
    $few = count(DB::getQueryLog());

    foreach (range(1, 6) as $i) {
        foreach (['2026-10-01', '2026-11-01'] as $day) {
            bankGroupPagesRow('PAGAMENTO POS SHOP NUMERO'.$i.' ROMA', '-9.00', $day);
        }
    }
    DB::flushQueryLog();
    $this->get(route('budget.bank.index'))->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual($few + 2);
});

it('a new filter from a group starts from its words and proposal', function (): void {
    bankGroupPagesSeed();
    $key = collect(resolve(BankGroups::class)->all())->firstWhere('name', 'Eni Station')->key;

    $this->get(route('budget.bank.filters.create', ['group' => $key]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('draft.id', null)
        ->where('draft.name', 'Eni Station')
        ->where('draft.excludes', ['COMMISSIONI'])
        ->where('draft.direction', 'expense')
        ->where('draft.financial_account_id', $this->account->id)
        ->where('draft.fee_mode', 'auto'));
});

it('the form of a group gives its movements as the examples', function (): void {
    bankGroupPagesSeed();
    $group = collect(resolve(BankGroups::class)->all())->firstWhere('name', 'Eni Station');

    $this->get(route('budget.bank.filters.create', ['group' => $group->key]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('example_ids', $group->transactions->pluck('id')->values()->all()));
});

it('an unknown group gives 404', function (): void {
    bankGroupPagesSeed();

    $this->get(route('budget.bank.filters.create', ['group' => 'nonexistent0000']))->assertNotFound();
});

const BANK_GROUP_PAGES_SATISPAY = 'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A. CID.LU08HOO0000000000000001642 MAN.SAT0023101207360112900';

/**
 * A planned expense of 13,99 € that the Netflix group would pay, and the signed-in member of the family.
 */
function bankGroupPagesMemberWithPayGroup(): void
{
    bankGroupPagesSeed();
    PlannedExpense::factory()->create(['family_id' => test()->marco->family_id, 'amount' => 13.99]);
    test()->actingAs(User::factory()->for(test()->marco->family)->create(['role' => Role::Member]));
}

it('a member sees no Pay proposal in the groups, and an admin still does', function (): void {
    bankGroupPagesSeed();
    PlannedExpense::factory()->create(['family_id' => $this->marco->family_id, 'amount' => 13.99]);

    $this->get(route('budget.bank.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('groups.0.proposal_action', 'pay'));

    bankGroupPagesMemberWithPayGroup();

    $this->get(route('budget.bank.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('groups.0.proposal_action', null)
        ->where('groups.0.proposal_detail', null));
});

it('a member sees nothing to pay in the rows of a group, and the form starts without an action', function (): void {
    bankGroupPagesMemberWithPayGroup();
    $key = collect(resolve(BankGroups::class)->all())->firstWhere('name', 'Netflix')->key;

    $this->get(route('budget.bank.index', ['group' => $key]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->reloadOnly('group_rows', fn (AssertableInertia $reload): AssertableInertia => $reload
            ->where('group_rows.0.will', 'none')
            ->where('group_rows.1.will', 'none')));
    $this->get(route('budget.bank.filters.create', ['group' => $key]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('draft.action', null)
        ->where('draft.name', 'Netflix'));
});

it('a group commission shows once, under its debit, and is not a lone row', function (): void {
    $debits = [];
    foreach (['2026-11-10', '2026-11-18'] as $day) {
        $debits[$day] = bankGroupPagesRow(BANK_GROUP_PAGES_SATISPAY, '-10.00', $day, ['counterparty' => 'Satispay Europe S.A.']);
    }
    $fee = bankGroupPagesRow('COMMISSIONI '.BANK_GROUP_PAGES_SATISPAY, '-0.40', '2026-11-19', ['counterparty' => 'Satispay Europe S.A.']);
    $key = resolve(BankGroups::class)->all()[0]->key;

    $this->get(route('budget.bank.index', ['group' => $key]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('groups', 1)
        ->has('rows', 0)
        ->where('counts', ['unsorted' => 3, 'groups' => 1, 'lone' => 0])
        ->reloadOnly('group_rows', fn (AssertableInertia $reload): AssertableInertia => $reload
            ->has('group_rows', 2)
            ->where('group_rows', fn ($rows): bool => collect($rows)->filter(fn (array $row): bool => ($row['fee']['id'] ?? null) === $fee->id)->pluck('transaction.id')->all() === [$debits['2026-11-18']->id]
                && collect($rows)->doesntContain(fn (array $row): bool => $row['transaction']['id'] === $fee->id))));
});

it('a commission between two lone debits shows once, under the nearer one, whichever block it is on', function (): void {
    $text = fn (string $name): string => 'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) '.$name.' CID.LU08HOO0000000000000001642';
    $newer = bankGroupPagesRow($text('Satispay Uno'), '-10.00', '2026-11-08', ['counterparty' => 'Satispay Uno']);
    $fee = bankGroupPagesRow('COMMISSIONI '.$text('Satispay Uno'), '-0.40', '2026-11-05', ['counterparty' => 'Satispay Uno']);
    $older = bankGroupPagesRow($text('Satispay Due'), '-10.00', '2026-11-03', ['counterparty' => 'Satispay Due']);

    foreach (range(1, 50) as $index) {
        bankGroupPagesRow('SHOP '.$index, '-5.00', '2026-11-07', ['counterparty' => 'Shop '.$index]);
    }

    $seen = [];
    foreach ([1, 2] as $block) {
        $this->get(route('budget.bank.index', ['page' => $block]))->assertInertia(function (AssertableInertia $page) use (&$seen, $fee): void {
            foreach ($page->toArray()['props']['rows'] as $row) {
                if ($row['id'] === $fee->id) {
                    $seen[] = 'row';
                }
                if (($row['fee']['id'] ?? null) === $fee->id) {
                    $seen[] = $row['id'];
                }
            }
        });
    }

    // The commission is 2 days from the older debit and 3 from the newer one, which is on the first block
    expect($seen)->toBe([$older->id])->and($newer->id)->not->toBe($older->id);
});

it('the key of a group of another family is 404', function (): void {
    bankGroupPagesSeed();
    $key = collect(resolve(BankGroups::class)->all())->firstWhere('name', 'Netflix')->key;

    $stranger = registerFamily('Rossi', 'anna@example.com');
    $stranger->family->forceFill(['bank_feed_enabled' => true])->save();
    $this->actingAs($stranger);

    $this->get(route('budget.bank.filters.create', ['group' => $key]))->assertNotFound();
});

/**
 * How many queries the request of the rows of a group asks (the partial reload of `group_rows`).
 */
function bankGroupPagesRowsQueries(string $key): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get(route('budget.bank.index', ['group' => $key]), ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(Request::create('/')), 'X-Inertia-Partial-Component' => 'budget/BankTransactions', 'X-Inertia-Partial-Data' => 'group_rows'])->assertOk();

    return count(DB::getQueryLog());
}

it('reads the rows of a group, with or without a proposal, with the queries of the group and not of each row', function (): void {
    foreach (range(1, 8) as $i) {
        foreach (['2026-10-01', '2026-11-01'] as $day) {
            bankGroupPagesRow('PAGAMENTO POS SHOP', '-9.00', $day, ['counterparty' => 'Shop']);
        }
    }
    foreach (['2026-10-02', '2026-11-02'] as $day) {
        bankGroupPagesRow('PAGAMENTO POS ALTRO', '-9.00', $day, ['counterparty' => 'Altro']);
    }
    $groups = collect(resolve(BankGroups::class)->all());
    $big = $groups->firstWhere('name', 'Shop')->key;
    $small = $groups->firstWhere('name', 'Altro')->key;
    bankGroupPagesRowsQueries($big);

    // No proposal: the same queries for 16 movements as for 2
    expect(bankGroupPagesRowsQueries($big))->toBe(bankGroupPagesRowsQueries($small));

    // A proposal that looks for the expense to link: its cost per movement stays small (the preview of a filter has the same)
    Expense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Spesa', 'date' => '2026-10-02', 'amount' => 9]);
    $base = bankGroupPagesRowsQueries($small);

    expect(bankGroupPagesRowsQueries($big) - $base)->toBeLessThanOrEqual(14);
});

it('the page reads the candidates of the groups once', function (): void {
    bankGroupPagesSeed();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->get(route('budget.bank.index'))->assertOk();
    $reads = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from `bank_transactions`') && str_contains($query['query'], 'limit 5000'))->count();

    expect($reads)->toBe(1);
});
