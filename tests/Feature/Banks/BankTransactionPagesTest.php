<?php

declare(strict_types=1);

use App\Contracts\BankFeed;
use App\Enums\BankConnectionStatus;
use App\Enums\BankTransactionStatus;
use App\Enums\Role;
use App\Exceptions\Banks\BankFeedFailed;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\Banks\FakeBankFeed;
use App\Support\Banks\BankEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['bank_feed.provider' => 'fake']);
    $this->marco = registerFamily();
    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $this->actingAs($this->marco);
});

/**
 * A connection of the family of an account, with the account it reads and a session the feed can use.
 *
 * @param  array<string, mixed>  $state  what to change in the connection
 */
function bankTransactionPagesConnection(User $user, array $state = [], string $name = 'Conto BancoPosta'): BankConnection
{
    return insideFamilyOf($user, function () use ($user, $state, $name): BankConnection {
        $connection = BankConnection::factory()->create(['family_id' => $user->family_id, 'institution_name' => 'BancoPosta', ...$state]);
        $connection->writeSession('session-'.$connection->id, CarbonImmutable::now()->addDays(100));
        FinancialAccount::factory()->create([
            'family_id' => $user->family_id,
            'bank_connection_id' => $connection->id,
            'bank_account_uid' => 'uid-'.$connection->id,
            'name' => $name,
            'last_four_digits' => '3307',
        ]);

        return $connection;
    });
}

/**
 * A transaction of the account of a connection.
 *
 * @param  array<string, mixed>  $state  what to change in the transaction
 */
function bankTransactionPagesRow(BankConnection $connection, array $state = []): BankTransaction
{
    return insideFamilyOf(test()->marco, fn (): BankTransaction => BankTransaction::factory()->create([
        'family_id' => $connection->family_id,
        'bank_connection_id' => $connection->id,
        'financial_account_id' => $connection->accounts()->firstOrFail()->id,
        ...$state,
    ]));
}

/**
 * Signs in a member of the family of Marco.
 */
function bankTransactionPagesMember(User $marco): User
{
    $member = User::factory()->for($marco->family)->create(['role' => Role::Member]);
    test()->actingAs($member);

    return $member;
}

test('the page is 404 while the feed is off', function (): void {
    $this->marco->family->forceFill(['bank_feed_enabled' => false])->save();

    $this->get(route('budget.bank.index'))->assertNotFound();
    $this->post(route('budget.bank.sync'))->assertNotFound();

    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    config(['bank_feed.provider' => '']);
    $this->get(route('budget.bank.index'))->assertNotFound();
});

it('lists the unsorted transactions, newest first, with the pending ones marked', function (): void {
    $connection = bankTransactionPagesConnection($this->marco);
    bankTransactionPagesRow($connection, ['text' => 'OLD', 'booked_on' => '2026-11-01', 'amount' => '-10.00']);
    bankTransactionPagesRow($connection, ['text' => 'NEW', 'booked_on' => '2026-11-05', 'amount' => '-72.40', 'counterparty' => 'EDISON']);
    bankTransactionPagesRow($connection, ['text' => 'WAITING', 'booked_on' => null, 'value_on' => '2026-11-06', 'status' => BankTransactionStatus::Pending, 'amount' => '-40.00']);
    // Already linked to an expense, ignored: not in the list
    bankTransactionPagesRow($connection, ['text' => 'LINKED', 'expense_id' => insideFamilyOf($this->marco, fn (): int => Expense::factory()->create(['family_id' => $this->marco->family_id])->id)]);
    bankTransactionPagesRow($connection, ['text' => 'SET ASIDE', 'ignored_at' => now()]);

    $this->get(route('budget.bank.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('budget/BankTransactions')
        ->where('show', 'unsorted')
        ->where('can_sort', true)
        ->where('can_change_banks', true)
        ->where('banks_to_reconnect', [])
        ->has('rows', 3)
        ->where('rows.0.text', 'WAITING')
        ->where('rows.0.pending', true)
        ->where('rows.0.booked_on', null)
        ->where('rows.0.value_on', '2026-11-06')
        ->where('rows.1.text', 'NEW')
        ->where('rows.1.pending', false)
        ->where('rows.1.booked_on', '2026-11-05')
        ->where('rows.1.counterparty', 'EDISON')
        ->where('rows.1.amount', -72.4)
        ->where('rows.1.account', 'Conto BancoPosta ·· 3307')
        ->where('rows.1.ignored', false)
        ->where('rows.1.recurring', false)
        ->where('rows.2.text', 'OLD')
        ->where('next', null));
});

it('gives 50 rows at a time and the number of the next block', function (): void {
    $connection = bankTransactionPagesConnection($this->marco);
    insideFamilyOf($this->marco, fn () => BankTransaction::factory()->count(52)->create([
        'family_id' => $connection->family_id,
        'bank_connection_id' => $connection->id,
        'financial_account_id' => $connection->accounts()->firstOrFail()->id,
    ]));

    $this->get(route('budget.bank.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('rows', 50)
        ->where('next', 2));
    $this->get(route('budget.bank.index', ['page' => 2]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('rows', 2)
        ->where('next', null));
});

it('keeps the ignored ones under their own filter', function (): void {
    $connection = bankTransactionPagesConnection($this->marco);
    bankTransactionPagesRow($connection, ['text' => 'TO SORT']);
    bankTransactionPagesRow($connection, ['text' => 'SET ASIDE', 'ignored_at' => now()]);

    $this->get(route('budget.bank.index', ['show' => 'ignored']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('show', 'ignored')
        ->has('rows', 1)
        ->where('rows.0.text', 'SET ASIDE')
        ->where('rows.0.ignored', true));

    $this->get(route('budget.bank.index', ['show' => 'nonsense']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('show', 'unsorted')
        ->where('rows.0.text', 'TO SORT'));
});

it('says when the banks were read last and at which hours they are read', function (): void {
    bankTransactionPagesConnection($this->marco, ['last_synced_at' => CarbonImmutable::parse('2026-11-20 07:00', 'Europe/Rome')]);
    bankTransactionPagesConnection($this->marco, ['institution_name' => 'Intesa', 'last_synced_at' => CarbonImmutable::parse('2026-11-20 13:00', 'Europe/Rome')]);

    $this->get(route('budget.bank.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('last_synced_at', '2026-11-20T13:00')
        ->where('sync_hours', [7, 13, 19]));
});

it('names the banks to connect again, once each', function (): void {
    bankTransactionPagesConnection($this->marco, ['status' => BankConnectionStatus::NeedsReconnect]);
    bankTransactionPagesConnection($this->marco, ['institution_name' => 'Intesa', 'status' => BankConnectionStatus::Error]);

    $this->get(route('budget.bank.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('banks_to_reconnect', ['BancoPosta']));
});

it('lets a member sort but not change the banks', function (): void {
    bankTransactionPagesMember($this->marco);

    $this->get(route('budget.bank.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('can_sort', true)
        ->where('can_change_banks', false));
});

it('never lists a transaction of another family', function (): void {
    $other = registerFamily('Rossi', 'rossi@example.com');
    $other->family->forceFill(['bank_feed_enabled' => true])->save();
    $theirs = bankTransactionPagesConnection($other, ['institution_name' => 'Intesa']);
    insideFamilyOf($other, fn (): BankTransaction => BankTransaction::factory()->create([
        'family_id' => $theirs->family_id,
        'bank_connection_id' => $theirs->id,
        'financial_account_id' => $theirs->accounts()->firstOrFail()->id,
        'text' => 'SECRET',
    ]));
    $this->actingAs($this->marco);

    $this->get(route('budget.bank.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('rows', 0));
});

test('refresh reads the bank with the visitor and says how many are new', function (): void {
    $feed = new FakeBankFeed;
    $feed->gives([
        new BankEntry('a', CarbonImmutable::parse('2026-11-18'), null, -5.0, 'EUR', 'One', null, true),
        new BankEntry('b', CarbonImmutable::parse('2026-11-19'), null, -6.0, 'EUR', 'Two', null, true),
        new BankEntry('c', CarbonImmutable::parse('2026-11-19'), null, -7.0, 'EUR', 'Three', null, true),
    ]);
    app()->instance(BankFeed::class, $feed);
    bankTransactionPagesConnection($this->marco);
    // A bank that has to be connected again is left alone
    bankTransactionPagesConnection($this->marco, ['institution_name' => 'Old', 'status' => BankConnectionStatus::NeedsReconnect]);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeaders(['User-Agent' => 'Phone'])
        ->from(route('budget.bank.index'))
        ->post(route('budget.bank.sync'))
        ->assertRedirect(route('budget.bank.index'))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', '3 new movements.');

    expect($feed->calls())->toBe(1)
        ->and($feed->visitors()[0]?->ip)->toBe('203.0.113.9')
        ->and($feed->visitors()[0]?->userAgent)->toBe('Phone')
        ->and(BankTransaction::query()->count())->toBe(3);

    // Nothing new the second time
    $this->from(route('budget.bank.index'))->post(route('budget.bank.sync'))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'No new movements.');
});

test('a member can refresh the list but not from the settings', function (): void {
    $feed = new FakeBankFeed;
    app()->instance(BankFeed::class, $feed);
    $connection = bankTransactionPagesConnection($this->marco);
    bankTransactionPagesMember($this->marco);

    $this->from(route('budget.bank.index'))->post(route('budget.bank.sync'))->assertRedirect();
    expect($feed->calls())->toBe(1);

    $this->post(route('banks.sync', $connection))->assertForbidden();
    expect($feed->calls())->toBe(1);
});

test('an admin refreshes one bank from the settings', function (): void {
    $feed = new FakeBankFeed;
    $feed->gives([new BankEntry('a', CarbonImmutable::parse('2026-11-18'), null, -5.0, 'EUR', 'One', null, true)]);
    app()->instance(BankFeed::class, $feed);
    $connection = bankTransactionPagesConnection($this->marco);
    $other = bankTransactionPagesConnection($this->marco, ['institution_name' => 'Intesa']);

    $this->from(route('banks.index'))->post(route('banks.sync', $connection))
        ->assertRedirect(route('banks.index'))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', '1 new movement.');

    expect($feed->calls())->toBe(1)
        ->and($feed->visitors()[0])->not->toBeNull();
    expect($other->transactions()->count())->toBe(0);
});

test('a connection of another family cannot be refreshed', function (): void {
    $feed = new FakeBankFeed;
    app()->instance(BankFeed::class, $feed);
    $other = registerFamily('Rossi', 'rossi@example.com');
    $other->family->forceFill(['bank_feed_enabled' => true])->save();
    $theirs = bankTransactionPagesConnection($other);
    $this->actingAs($this->marco);

    $this->post(route('banks.sync', $theirs))->assertNotFound();
    expect($feed->calls())->toBe(0);
});

test('both refreshes are limited to six a minute', function (): void {
    app()->instance(BankFeed::class, new FakeBankFeed);
    bankTransactionPagesConnection($this->marco);

    foreach (range(1, 6) as $ignored) {
        $this->post(route('budget.bank.sync'))->assertRedirect();
    }

    $this->post(route('budget.bank.sync'))->assertStatus(429);
});

test('the bank list not being available says so, not that the bank is wrong', function (): void {
    $feed = Mockery::mock(BankFeed::class);
    $feed->shouldReceive('isActive')->andReturn(true);
    $feed->shouldReceive('institutions')->andThrow(new BankFeedFailed('down'));
    app()->instance(BankFeed::class, $feed);
    Cache::forget('bank-institutions:IT');

    $this->post(route('banks.store'), ['institution' => 'Banca di prova'])
        ->assertSessionHasErrors(['institution' => 'We cannot load the list of banks right now. Try again in a moment.']);
});
