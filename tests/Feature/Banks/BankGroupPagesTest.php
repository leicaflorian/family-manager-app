<?php

declare(strict_types=1);

use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\PlannedExpense;
use App\Services\Banks\BankGroups;
use Carbon\CarbonImmutable;
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

it('an unknown group gives 404', function (): void {
    bankGroupPagesSeed();

    $this->get(route('budget.bank.filters.create', ['group' => 'nonexistent0000']))->assertNotFound();
});
