<?php

declare(strict_types=1);

use App\Contracts\BankFeed;
use App\Enums\FinancialAccountType;
use App\Enums\Role;
use App\Exceptions\Banks\BankFeedFailed;
use App\Jobs\SyncBank;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Services\Banks\FakeBankFeed;
use App\Support\Banks\BankInstitution;
use App\Support\Banks\BankSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

beforeEach(function (): void {
    config(['bank_feed.provider' => 'fake']);
    Cache::forget('bank-institutions:IT');
    app()->instance(BankFeed::class, new FakeBankFeed);
    $this->marco = registerFamily();
    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $this->actingAs($this->marco);
});

/**
 * A bank feed that answers as the fake does, except for the calls the test sets up in `$change`.
 *
 * @param  (callable(MockInterface): void)|null  $change  sets up the calls that differ
 */
function bankConnectFeed(?callable $change = null): MockInterface
{
    $fake = new FakeBankFeed;
    $feed = Mockery::mock(BankFeed::class);
    // Mockery answers with the first expectation that fits: what the test changes comes before the defaults
    if ($change !== null) {
        $change($feed);
    }
    $feed->shouldReceive('isActive')->andReturn(true);
    $feed->shouldReceive('institutions')->andReturnUsing($fake->institutions(...));
    $feed->shouldReceive('start')->andReturnUsing($fake->start(...));
    $feed->shouldReceive('complete')->andReturnUsing($fake->complete(...));
    app()->instance(BankFeed::class, $feed);

    return $feed;
}

/**
 * Starts a connection to the one bank of the fake and returns the state kept in the session.
 */
function bankConnectStart(): string
{
    test()->post(route('banks.store'), ['institution' => 'Banca di prova']);

    return (string) session('bank_connect.state');
}

/**
 * The failure the person sees when the callback is opened badly.
 */
function bankConnectAssertFailed(mixed $response): void
{
    $response->assertRedirect(route('banks.index'))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'The connection did not work. Try again.');
    expect(BankConnection::query()->count())->toBe(0);
}

test('an admin is sent to the bank and the state is kept in the session', function (): void {
    $response = $this->withHeader('X-Inertia', 'true')->post(route('banks.store'), ['institution' => 'Banca di prova']);

    $state = session('bank_connect.state');
    expect($state)->toBeString()->toHaveLength(40)
        ->and(session('bank_connect.institution'))->toBe('Banca di prova');
    $response->assertStatus(409)->assertHeader('X-Inertia-Location', route('banks.callback').'?'.http_build_query(['code' => 'fake', 'state' => $state]));
});

test('the consent lasts at most what the bank allows', function (): void {
    $feed = Mockery::mock(BankFeed::class);
    $feed->shouldReceive('isActive')->andReturn(true);
    $feed->shouldReceive('institutions')->andReturn([new BankInstitution('Banca di prova', 'IT', null, 90)]);
    $feed->shouldReceive('start')->once()->withArgs(fn ($bank, $state, $url, CarbonImmutable $until): bool => $until->isSameDay(now()->addDays(90)))->andReturn('https://bank.test');
    app()->instance(BankFeed::class, $feed);

    $this->withHeader('X-Inertia', 'true')->post(route('banks.store'), ['institution' => 'Banca di prova'])->assertStatus(409);
});

test('a member cannot start a connection', function (): void {
    $member = User::factory()->for($this->marco->family)->create(['role' => Role::Member]);

    $this->actingAs($member)->post(route('banks.store'), ['institution' => 'Banca di prova'])->assertForbidden();
});

test('a bank that is not in the list is refused', function (): void {
    $this->post(route('banks.store'), ['institution' => 'Banca che non esiste'])->assertSessionHasErrors('institution');
    $this->post(route('banks.store'), [])->assertSessionHasErrors('institution');
    expect(session('bank_connect'))->toBeNull();
});

test('starting is a 404 while the feed is off', function (): void {
    $this->marco->family->forceFill(['bank_feed_enabled' => false])->save();

    $this->post(route('banks.store'), ['institution' => 'Banca di prova'])->assertNotFound();
    $this->get(route('banks.callback'))->assertNotFound();
});

test('the callback creates the connection, links the account with the same last four digits and queues a sync', function (): void {
    Queue::fake();
    $existing = insideFamilyOf($this->marco, fn (): FinancialAccount => FinancialAccount::factory()->create(['family_id' => $this->marco->family_id, 'name' => 'Conto BPER', 'last_four_digits' => '4821']));
    $state = bankConnectStart();

    $this->get(route('banks.callback', ['code' => 'fake', 'state' => $state]))
        ->assertRedirect(route('banks.index'))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Conto BPER ·· 4821 connected.');

    $connection = BankConnection::query()->sole();
    expect($connection->institution_name)->toBe('Banca di prova')
        ->and($connection->provider)->toBe('fake')
        ->and($connection->sessionId())->toBe('fake-session')
        ->and($connection->valid_until)->not->toBeNull()
        ->and($connection->last_error)->toBeNull()
        ->and($connection->status->value)->toBe('active');

    $existing->refresh();
    expect($existing->bank_connection_id)->toBe($connection->id)
        ->and($existing->bank_account_key)->toBe('fake-account-key')
        ->and($existing->bank_account_uid)->toBe('fake-account')
        ->and(FinancialAccount::query()->count())->toBe(1)
        ->and(session('bank_connect'))->toBeNull();
    Queue::assertPushed(SyncBank::class, 1);
});

test('a bank that shares no account says so, not that zero accounts are connected', function (): void {
    Queue::fake();
    // The trial bank, except that the session it gives has no account
    $fake = new FakeBankFeed;
    $feed = Mockery::mock(BankFeed::class);
    $feed->shouldReceive('isActive')->andReturn(true);
    $feed->shouldReceive('institutions')->andReturnUsing(fn (string $country): array => $fake->institutions($country));
    $feed->shouldReceive('start')->andReturnUsing(fn (BankInstitution $bank, string $state, string $url, CarbonImmutable $until): string => $fake->start($bank, $state, $url, $until));
    $feed->shouldReceive('complete')->andReturn(new BankSession('fake-session', CarbonImmutable::now()->addDays(180), 'Banca di prova', []));
    app()->instance(BankFeed::class, $feed);
    $state = bankConnectStart();

    $this->get(route('banks.callback', ['code' => 'fake', 'state' => $state]))
        ->assertRedirect(route('banks.index'))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'The bank is connected, but it gave no account we can read.');
});

test('an account with no match is created with the name of the bank', function (): void {
    Queue::fake();
    $organization = insideFamilyOf($this->marco, fn (): Organization => Organization::factory()->create(['family_id' => $this->marco->family_id, 'name' => 'Banca di prova']));
    $person = insideFamilyOf($this->marco, fn (): Person => Person::query()->where('user_id', $this->marco->id)->sole());
    $state = bankConnectStart();

    $this->get(route('banks.callback', ['code' => 'fake', 'state' => $state]))
        ->assertInertiaFlash('toast.message', 'Account Banca di prova ·· 4821 connected.');

    $account = FinancialAccount::query()->sole();
    expect($account->name)->toBe('Account Banca di prova')
        ->and($account->type)->toBe(FinancialAccountType::CheckingAccount)
        ->and($account->last_four_digits)->toBe('4821')
        ->and($account->organization_id)->toBe($organization->id)
        ->and($account->person_id)->toBe($person->id)
        ->and($account->bank_connection_id)->toBe(BankConnection::query()->sole()->id);
});

test('an account already linked by its key keeps being the same account', function (): void {
    Queue::fake();
    $kept = insideFamilyOf($this->marco, fn (): FinancialAccount => tap(FinancialAccount::factory()->create(['family_id' => $this->marco->family_id, 'last_four_digits' => '9999']), function (FinancialAccount $account): void {
        $account->forceFill(['bank_account_key' => 'fake-account-key'])->save();
    }));
    $state = bankConnectStart();

    $this->get(route('banks.callback', ['code' => 'fake', 'state' => $state]));

    expect(FinancialAccount::query()->count())->toBe(1)
        ->and($kept->refresh()->bank_connection_id)->toBe(BankConnection::query()->sole()->id);
});

test('connecting the same bank again updates the connection and keeps its transactions', function (): void {
    Queue::fake();
    $state = bankConnectStart();
    $this->get(route('banks.callback', ['code' => 'fake', 'state' => $state]));
    $connection = BankConnection::query()->sole();
    $account = FinancialAccount::query()->sole();
    BankTransaction::factory()->create(['family_id' => $this->marco->family_id, 'financial_account_id' => $account->id, 'bank_connection_id' => $connection->id]);
    $connection->forceFill(['status' => 'needs_reconnect', 'last_error' => 'Consent ended', 'expiry_notified_at' => now()])->save();

    $state = bankConnectStart();
    $this->get(route('banks.callback', ['code' => 'fake', 'state' => $state]))->assertRedirect(route('banks.index'));

    $connection->refresh();
    expect(BankConnection::query()->count())->toBe(1)
        ->and($connection->status->value)->toBe('active')
        ->and($connection->last_error)->toBeNull()
        ->and($connection->expiry_notified_at)->toBeNull()
        ->and(BankTransaction::query()->count())->toBe(1)
        ->and(FinancialAccount::query()->count())->toBe(1);
});

test('an account that does not come back stays, without the id used in the calls', function (): void {
    Queue::fake();
    $state = bankConnectStart();
    $this->get(route('banks.callback', ['code' => 'fake', 'state' => $state]));
    $connection = BankConnection::query()->sole();
    $gone = insideFamilyOf($this->marco, fn (): FinancialAccount => tap(FinancialAccount::factory()->create(['family_id' => $this->marco->family_id, 'last_four_digits' => '0001']), function (FinancialAccount $account) use ($connection): void {
        $account->forceFill(['bank_connection_id' => $connection->id, 'bank_account_key' => 'old-key', 'bank_account_uid' => 'old-uid'])->save();
    }));

    $state = bankConnectStart();
    $this->get(route('banks.callback', ['code' => 'fake', 'state' => $state]));

    $gone->refresh();
    expect($gone->bank_account_uid)->toBeNull()
        ->and($gone->bank_account_key)->toBe('old-key')
        ->and($gone->bank_connection_id)->toBe($connection->id);
});

test('a callback without state, with a wrong state, used twice, or with an error creates nothing', function (): void {
    Queue::fake();

    // No state at all, nothing started
    bankConnectAssertFailed($this->get(route('banks.callback', ['code' => 'fake'])));

    // A wrong state
    bankConnectStart();
    bankConnectAssertFailed($this->get(route('banks.callback', ['code' => 'fake', 'state' => 'wrong'])));

    // The state of a started connection, but the bank says no
    $state = bankConnectStart();
    bankConnectAssertFailed($this->get(route('banks.callback', ['error' => 'access_denied', 'state' => $state])));

    // Used twice: the first one works, the second finds the state gone
    $state = bankConnectStart();
    $this->get(route('banks.callback', ['code' => 'fake', 'state' => $state]))->assertInertiaFlash('toast.type', 'success');
    BankConnection::query()->delete();
    $this->get(route('banks.callback', ['code' => 'fake', 'state' => $state]))->assertInertiaFlash('toast.type', 'error');
    expect(BankConnection::query()->count())->toBe(0);
    Queue::assertPushed(SyncBank::class, 1);
});

test('a callback with a state but no code, or a provider that fails, creates nothing', function (): void {
    $state = bankConnectStart();
    bankConnectAssertFailed($this->get(route('banks.callback', ['state' => $state])));

    bankConnectFeed(fn ($feed) => $feed->shouldReceive('complete')->andThrow(new BankFeedFailed('down')));
    $state = bankConnectStart();
    bankConnectAssertFailed($this->get(route('banks.callback', ['code' => 'fake', 'state' => $state])));
});

test('the callback of another family\'s session cannot complete it', function (): void {
    $state = bankConnectStart();
    $rossi = registerFamily('Rossi', 'rossi@example.com');
    $rossi->family->forceFill(['bank_feed_enabled' => true])->save();

    // The state lives in the session of Marco: a person of another family has none
    $this->flushSession();
    $this->actingAs($rossi)->get(route('banks.callback', ['code' => 'fake', 'state' => $state]))
        ->assertRedirect(route('banks.index'))
        ->assertInertiaFlash('toast.type', 'error');
    expect(BankConnection::query()->count())->toBe(0);
});

test('a member cannot complete a connection', function (): void {
    $member = User::factory()->for($this->marco->family)->create(['role' => Role::Member]);

    $this->actingAs($member)->get(route('banks.callback', ['code' => 'fake', 'state' => 'x']))->assertForbidden();
});

/**
 * A connection with a session, an account and two transactions, one of them paying an expense.
 *
 * @return array{BankConnection, FinancialAccount}
 */
function bankConnectLinked(User $user): array
{
    return insideFamilyOf($user, function () use ($user): array {
        $connection = BankConnection::factory()->create(['family_id' => $user->family_id]);
        $connection->writeSession('fake-session', CarbonImmutable::now()->addDays(100));
        $account = FinancialAccount::factory()->create(['family_id' => $user->family_id]);
        $account->forceFill(['bank_connection_id' => $connection->id, 'bank_account_key' => 'k', 'bank_account_uid' => 'u'])->save();
        BankTransaction::factory()->create(['family_id' => $user->family_id, 'financial_account_id' => $account->id, 'bank_connection_id' => $connection->id]);
        $expense = Expense::factory()->create(['family_id' => $user->family_id]);
        BankTransaction::factory()->create(['family_id' => $user->family_id, 'financial_account_id' => $account->id, 'bank_connection_id' => $connection->id, 'expense_id' => $expense->id]);

        return [$connection, $account];
    });
}

test('disconnecting revokes the consent, unlinks the accounts and keeps the linked transactions', function (): void {
    [$connection, $account] = bankConnectLinked($this->marco);
    bankConnectFeed(fn ($feed) => $feed->shouldReceive('revoke')->once()->with('fake-session'));

    $this->delete(route('banks.destroy', $connection))->assertRedirect(route('banks.index'))
        ->assertInertiaFlash('toast.type', 'success');

    $account->refresh();
    expect(BankConnection::query()->count())->toBe(0)
        ->and($account->bank_connection_id)->toBeNull()
        ->and($account->bank_account_key)->toBeNull()
        ->and($account->bank_account_uid)->toBeNull()
        ->and(BankTransaction::query()->count())->toBe(1)
        ->and(BankTransaction::query()->sole()->expense_id)->not->toBeNull()
        ->and(BankTransaction::query()->sole()->bank_connection_id)->toBeNull();
});

test('disconnecting still works when the provider fails', function (): void {
    [$connection] = bankConnectLinked($this->marco);
    bankConnectFeed(fn ($feed) => $feed->shouldReceive('revoke')->andThrow(new BankFeedFailed('down')));

    $this->delete(route('banks.destroy', $connection))->assertRedirect(route('banks.index'));

    expect(BankConnection::query()->count())->toBe(0);
});

test('a member cannot disconnect, and a connection of another family answers 404', function (): void {
    [$connection] = bankConnectLinked($this->marco);
    $rossi = registerFamily('Rossi', 'rossi@example.com');
    $rossi->family->forceFill(['bank_feed_enabled' => true])->save();
    $member = User::factory()->for($this->marco->family)->create(['role' => Role::Member]);

    $this->actingAs($member)->delete(route('banks.destroy', $connection))->assertForbidden();
    $this->actingAs($rossi)->delete(route('banks.destroy', $connection))->assertNotFound();
    expect(BankConnection::query()->withoutGlobalScopes()->count())->toBe(1);
});

test('a session that cannot be read is no reason to keep the connection', function (): void {
    $connection = insideFamilyOf($this->marco, fn (): BankConnection => BankConnection::factory()->create(['family_id' => $this->marco->family_id]));

    $this->delete(route('banks.destroy', $connection))->assertRedirect(route('banks.index'));

    expect(BankConnection::query()->count())->toBe(0);
});
