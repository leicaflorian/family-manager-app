<?php

declare(strict_types=1);

use App\Contracts\BankFeed;
use App\Enums\BankConnectionStatus;
use App\Enums\Role;
use App\Exceptions\Banks\BankFeedFailed;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\Reminders\CardNotices;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['bank_feed.provider' => 'fake']);
    Cache::forget('bank-institutions:IT');
    $this->marco = registerFamily();
    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $this->actingAs($this->marco);
});

/**
 * The headers of a request that asks only for the institutions, as the page does when it opens the panel.
 *
 * @return array<string, string>
 */
function bankPagesAsk(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(Request::create('/')),
        'X-Inertia-Partial-Component' => 'settings/Banks',
        'X-Inertia-Partial-Data' => 'institutions',
    ];
}

/**
 * Signs in a member of the family of Marco.
 */
function bankPagesMember(User $marco): User
{
    $member = User::factory()->for($marco->family)->create(['role' => Role::Member]);
    test()->actingAs($member);

    return $member;
}

/**
 * A connection of the family of an account, with the account it reads.
 *
 * @param  array<string, mixed>  $state  what to change in the connection
 */
function bankPagesConnection(User $user, array $state = [], string $account = 'Conto BancoPosta', string $digits = '3307'): BankConnection
{
    return insideFamilyOf($user, function () use ($user, $state, $account, $digits): BankConnection {
        $connection = BankConnection::factory()->create(['family_id' => $user->family_id, 'institution_name' => 'BancoPosta', ...$state]);
        FinancialAccount::factory()->create(['family_id' => $user->family_id, 'bank_connection_id' => $connection->id, 'name' => $account, 'last_four_digits' => $digits]);

        return $connection;
    });
}

test('the page answers 404 while the feed is off for the family', function (): void {
    $this->marco->family->forceFill(['bank_feed_enabled' => false])->save();
    $this->get(route('banks.index'))->assertNotFound();

    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    config(['bank_feed.provider' => '']);
    $this->get(route('banks.index'))->assertNotFound();
});

it('lists the connections of the family only', function (): void {
    bankPagesConnection($this->marco, [
        'valid_until' => '2027-05-18',
        'last_synced_at' => CarbonImmutable::parse('2026-11-20 13:00', 'Europe/Rome'),
    ]);
    $other = registerFamily('Rossi', 'rossi@example.com');
    bankPagesConnection($other, ['institution_name' => 'Intesa']);

    $this->get(route('banks.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('settings/Banks')
        ->where('can_change', true)
        ->has('connections', 1)
        ->where('connections.0.institution', 'BancoPosta')
        ->where('connections.0.status', 'active')
        ->where('connections.0.valid_until', '2027-05-18')
        ->where('connections.0.last_synced_at', '2026-11-20T13:00')
        ->where('connections.0.accounts', ['Conto BancoPosta ·· 3307'])
        ->where('connections.0.syncing', false)
        ->where('sync_hours', [7, 13, 19])
        ->where('consent_days', 180)
        ->missing('institutions'));
});

it('says a connection is syncing until the first read is done', function (): void {
    bankPagesConnection($this->marco, ['last_synced_at' => null]);
    bankPagesConnection($this->marco, ['institution_name' => 'Buddy Bank', 'status' => BankConnectionStatus::NeedsReconnect, 'last_synced_at' => null]);

    $this->get(route('banks.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('connections.0.syncing', true)
        ->where('connections.0.valid_until', null)
        ->where('connections.0.last_synced_at', null)
        ->where('connections.1.status', 'needs_reconnect')
        ->where('connections.1.syncing', false));
});

test('a member sees the banks but cannot change them', function (): void {
    bankPagesConnection($this->marco);
    bankPagesMember($this->marco);

    $this->get(route('banks.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('can_change', false)
        ->has('connections', 1));
});

test('the institutions come only when asked and are cached', function (): void {
    $this->get(route('banks.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->missing('institutions'));
    expect(Cache::has('bank-institutions:IT'))->toBeFalse();

    $this->get(route('banks.index'), bankPagesAsk())
        ->assertJsonPath('props.institutions.0.name', 'Banca di prova')
        ->assertJsonPath('props.institutions.0.country', 'IT')
        ->assertJsonPath('props.institutions.0.logo', null);
    expect(Cache::has('bank-institutions:IT'))->toBeTrue();

    // A second ask is answered from the cache, not by the provider
    Cache::put('bank-institutions:IT', [['name' => 'Poste', 'country' => 'IT', 'logo' => 'https://logo.test/poste.png', 'max_consent_days' => 180]], 60);
    $this->get(route('banks.index'), bankPagesAsk())
        ->assertJsonPath('props.institutions.0.name', 'Poste')
        ->assertJsonPath('props.institutions.0.logo', 'https://logo.test/poste.png');
});

test('a provider that does not answer gives no list and nothing is cached', function (): void {
    $feed = Mockery::mock(BankFeed::class);
    $feed->shouldReceive('isActive')->andReturn(true);
    $feed->shouldReceive('institutions')->andThrow(new BankFeedFailed('down'));
    app()->instance(BankFeed::class, $feed);

    $this->get(route('banks.index'), bankPagesAsk())
        ->assertJsonPath('props.institutions', null);
    expect(Cache::has('bank-institutions:IT'))->toBeFalse();
});

test('the shared prop counts the unsorted transactions', function (): void {
    $connection = bankPagesConnection($this->marco);
    $account = FinancialAccount::query()->where('bank_connection_id', $connection->id)->sole();
    BankTransaction::factory()->count(2)->create(['family_id' => $this->marco->family_id, 'financial_account_id' => $account->id, 'bank_connection_id' => $connection->id]);
    BankTransaction::factory()->create(['family_id' => $this->marco->family_id, 'financial_account_id' => $account->id, 'bank_connection_id' => $connection->id, 'ignored_at' => now()]);

    $this->get(route('settings'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('bank_feed.unsorted', 2));
});

test('the shared prop is null when the feed is off', function (): void {
    $this->marco->family->forceFill(['bank_feed_enabled' => false])->save();

    $this->get(route('settings'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('bank_feed', null));
});

test('the settings tell whether the family can link banks', function (): void {
    $this->get(route('settings'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('bankCount', 0));

    bankPagesConnection($this->marco);
    $this->get(route('settings'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('bankCount', 1));
});

test('the settings do not count the banks while the feed is off', function (): void {
    bankPagesConnection($this->marco);
    $this->marco->family->forceFill(['bank_feed_enabled' => false])->save();

    $this->get(route('settings'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('bankCount', 0));
});

test('an account not linked shows the notice to link it, a linked one does not', function (): void {
    $linked = bankPagesConnection($this->marco);
    $loose = insideFamilyOf($this->marco, fn (): FinancialAccount => FinancialAccount::factory()->create(['family_id' => $this->marco->family_id]));
    $today = CarbonImmutable::today('Europe/Rome');

    $notices = insideFamilyOf($this->marco, fn (): array => resolve(CardNotices::class)->for($loose, $today, $this->marco));
    $notice = collect($notices)->firstWhere('key', 'link-bank');

    expect($notice)->not->toBeNull()
        ->and($notice->title)->toBe('Connect this account to the bank')
        ->and($notice->action_label)->toBe('Connect')
        ->and($notice->action_href)->toBe(route('banks.index', absolute: false));

    $account = FinancialAccount::query()->where('bank_connection_id', $linked->id)->sole();
    expect(collect(insideFamilyOf($this->marco, fn (): array => resolve(CardNotices::class)->for($account, $today, $this->marco)))->firstWhere('key', 'link-bank'))->toBeNull();
});

test('the notice to link an account is not shown with the feed off, to a member or on a closed account', function (): void {
    $loose = insideFamilyOf($this->marco, fn (): FinancialAccount => FinancialAccount::factory()->create(['family_id' => $this->marco->family_id]));
    $today = CarbonImmutable::today('Europe/Rome');
    $member = User::factory()->for($this->marco->family)->create(['role' => Role::Member]);
    $has = fn (?User $user): bool => collect(insideFamilyOf($this->marco, fn (): array => resolve(CardNotices::class)->for($loose->fresh(), $today, $user)))->contains('key', 'link-bank');

    expect($has($member))->toBeFalse()
        ->and($has($this->marco))->toBeTrue();

    $this->marco->family->forceFill(['bank_feed_enabled' => false])->save();
    expect($has($this->marco))->toBeFalse();

    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $loose->forceFill(['status' => 'closed'])->save();
    expect($has($this->marco))->toBeFalse();
});
