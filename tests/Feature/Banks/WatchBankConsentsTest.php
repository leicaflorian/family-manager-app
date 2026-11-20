<?php

declare(strict_types=1);

use App\Actions\Banks\CompleteBankConnection;
use App\Actions\Banks\SyncBankConnection;
use App\Contracts\BankFeed;
use App\Enums\BankConnectionStatus;
use App\Exceptions\Banks\BankConsentEnded;
use App\Jobs\SyncBank;
use App\Mail\BankConsentExpiringMail;
use App\Models\BankConnection;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Services\Banks\FakeBankFeed;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-11 10:00', 'Europe/Rome'));
    $this->family = Family::factory()->create(['bank_feed_enabled' => true]);
    $this->admin = User::factory()->for($this->family)->admin()->create(['locale' => 'it']);
    $this->other = User::factory()->for($this->family)->admin()->create(['locale' => 'en']);
    $this->member = User::factory()->for($this->family)->create();
});

/**
 * A connection of the family of the test that ends in some days, with a readable session and an account.
 *
 * @param  int  $days  days left of the consent
 * @param  array<string, mixed>  $attributes  changes to the connection
 */
function bankWatchConnection(int $days, array $attributes = []): BankConnection
{
    return CurrentFamily::using(test()->family->id, function () use ($days, $attributes): BankConnection {
        $connection = BankConnection::factory()->create([
            'family_id' => test()->family->id,
            'institution_name' => 'BancoPosta',
            'valid_until' => now()->addDays($days),
            ...$attributes,
        ]);
        $connection->writeSession('session', now()->addDays($days));
        FinancialAccount::factory()->create(['family_id' => test()->family->id, 'bank_connection_id' => $connection->id, 'bank_account_uid' => 'uid']);

        return $connection->refresh();
    });
}

/**
 * The notices about connections of the family of the test, for an account.
 *
 * @param  User  $user  the account
 * @return list<ReminderNotice>
 */
function bankWatchNotices(User $user): array
{
    return CurrentFamily::using($user->family_id, fn (): array => ReminderNotice::query()
        ->whereNotNull('bank_connection_id')->where('user_id', $user->id)->orderBy('id')->get()->all());
}

test('seven days before the end every admin gets a notice and a mail, once', function (): void {
    Queue::fake([SyncBank::class]);
    Mail::fake();
    $connection = bankWatchConnection(7);

    $this->artisan('banks:sync')->assertSuccessful();
    $this->artisan('banks:sync')->assertSuccessful();

    $notices = bankWatchNotices($this->admin);
    expect($notices)->toHaveCount(1)
        ->and($notices[0]->bank_connection_id)->toBe($connection->id)
        ->and($notices[0]->lines)->toBe(['Il collegamento con BancoPosta scade il 18/05/2026. Ricollegalo per continuare a ricevere i movimenti.'])
        ->and(bankWatchNotices($this->other)[0]->lines)->toBe(['The link with BancoPosta ends on 18/05/2026. Connect it again to keep receiving the transactions.'])
        ->and($connection->refresh()->expiry_notified_at)->not->toBeNull();
    Mail::assertQueued(BankConsentExpiringMail::class, 2);
    Mail::assertQueued(BankConsentExpiringMail::class, fn (BankConsentExpiringMail $mail): bool => $mail->hasTo($this->admin->email) && $mail->locale === 'it');
    Mail::assertQueued(BankConsentExpiringMail::class, fn (BankConsentExpiringMail $mail): bool => $mail->hasTo($this->other->email) && $mail->locale === 'en');
});

test('a consent with more than a week left says nothing', function (): void {
    Queue::fake([SyncBank::class]);
    Mail::fake();
    bankWatchConnection(8);

    $this->artisan('banks:sync')->assertSuccessful();

    expect(bankWatchNotices($this->admin))->toBeEmpty();
    Mail::assertNothingQueued();
});

test('a member gets nothing', function (): void {
    Queue::fake([SyncBank::class]);
    Mail::fake();
    bankWatchConnection(3);

    $this->artisan('banks:sync')->assertSuccessful();

    expect(bankWatchNotices($this->member))->toBeEmpty();
    Mail::assertNotQueued(BankConsentExpiringMail::class, fn (BankConsentExpiringMail $mail): bool => $mail->hasTo($this->member->email));
});

test('after the end the bank is to reconnect and is not read', function (): void {
    Queue::fake([SyncBank::class]);
    Mail::fake();
    $connection = bankWatchConnection(-1);

    $this->artisan('banks:sync')->assertSuccessful();

    expect($connection->refresh()->status)->toBe(BankConnectionStatus::NeedsReconnect);
    Queue::assertNotPushed(SyncBank::class);
});

test('reconnecting clears the warning so the next one is sent in six months', function (): void {
    Queue::fake([SyncBank::class]);
    Mail::fake();
    $connection = bankWatchConnection(5);
    $this->artisan('banks:sync')->assertSuccessful();
    expect($connection->refresh()->expiry_notified_at)->not->toBeNull();

    $this->travelTo(CarbonImmutable::parse('2026-05-19 10:00', 'Europe/Rome'));
    $this->artisan('banks:sync')->assertSuccessful();
    expect($connection->refresh()->status)->toBe(BankConnectionStatus::NeedsReconnect);

    config(['bank_feed.provider' => 'fake']);
    app()->instance(BankFeed::class, new FakeBankFeed);
    CurrentFamily::using($this->family->id, function () use ($connection): void {
        $connection->forceFill(['institution_name' => 'Banca di prova'])->save();
        resolve(CompleteBankConnection::class)->handle('code');
    });
    $connection->refresh();

    expect($connection->status)->toBe(BankConnectionStatus::Active)
        ->and($connection->expiry_notified_at)->toBeNull()
        ->and($connection->valid_until?->toDateString())->toBe('2026-11-15');

    $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00', 'Europe/Rome'));
    $this->artisan('banks:sync')->assertSuccessful();

    expect(bankWatchNotices($this->admin))->toHaveCount(2);
    Mail::assertQueued(BankConsentExpiringMail::class, 4);
});

test('a consent ended by the bank is told once, not at every sync', function (): void {
    Mail::fake();
    $connection = bankWatchConnection(60);
    $feed = new FakeBankFeed;
    $feed->failsWith(new BankConsentEnded('revoked'));
    app()->instance(BankFeed::class, $feed);

    foreach ([1, 2, 3] as $_) {
        CurrentFamily::using($this->family->id, fn (): int => resolve(SyncBankConnection::class)->handle($connection->refresh()));
    }

    $notices = bankWatchNotices($this->admin);
    expect($notices)->toHaveCount(1)
        ->and($notices[0]->lines)->toBe(['BancoPosta ha tolto il collegamento. Ricollegalo per continuare a ricevere i movimenti.'])
        ->and(bankWatchNotices($this->member))->toBeEmpty()
        ->and($connection->refresh()->status)->toBe(BankConnectionStatus::NeedsReconnect);
    Mail::assertQueued(BankConsentExpiringMail::class, 2);
});

test('a consent that ended after the warning is not told again by the sync', function (): void {
    Mail::fake();
    $connection = bankWatchConnection(5, ['expiry_notified_at' => now()]);
    $feed = new FakeBankFeed;
    $feed->failsWith(new BankConsentEnded('revoked'));
    app()->instance(BankFeed::class, $feed);

    CurrentFamily::using($this->family->id, fn (): int => resolve(SyncBankConnection::class)->handle($connection));

    expect(bankWatchNotices($this->admin))->toBeEmpty();
    Mail::assertNothingQueued();
});
