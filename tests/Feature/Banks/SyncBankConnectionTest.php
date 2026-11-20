<?php

declare(strict_types=1);

use App\Actions\Banks\SyncBankConnection;
use App\Contracts\BankFeed;
use App\Enums\BankConnectionStatus;
use App\Exceptions\Banks\BankConsentEnded;
use App\Exceptions\Banks\BankFeedFailed;
use App\Exceptions\Banks\BankLimitReached;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Services\Banks\FakeBankFeed;
use App\Support\Banks\BankEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Exceptions;

beforeEach(function (): void {
    registerFamily();
    $this->feed = new FakeBankFeed;
    app()->instance(BankFeed::class, $this->feed);
});

/**
 * A connection of the Bianchi with a readable session and one account that has a bank uid.
 *
 * @param  array<string, mixed>  $attributes  changes to the connection
 * @return array{BankConnection, FinancialAccount}
 */
function bankSyncSetup(array $attributes = [], ?string $uid = 'uid-1', bool $session = true): array
{
    $familyId = Family::query()->where('name', 'Bianchi')->sole()->id;
    $connection = BankConnection::factory()->create(['family_id' => $familyId, ...$attributes]);

    if ($session) {
        $connection->writeSession('session-'.$connection->id, CarbonImmutable::now()->addDays(100));
    }

    $account = FinancialAccount::factory()->create(['family_id' => $familyId, 'bank_connection_id' => $connection->id, 'bank_account_uid' => $uid]);

    return [$connection->refresh(), $account];
}

test('the first sync asks for 90 days, the next from five days before the last one', function (): void {
    insideBianchi(function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', 'Europe/Rome'));
        [$connection] = bankSyncSetup();
        $this->feed->gives([new BankEntry('a', CarbonImmutable::parse('2026-11-19'), null, -5.0, 'EUR', 'Coffee', null, true)]);

        $count = resolve(SyncBankConnection::class)->handle($connection);

        expect($count)->toBe(1)
            ->and($this->feed->requests()[0]['from']->toDateString())->toBe('2026-08-22')
            ->and($this->feed->requests()[0]['to']->toDateString())->toBe('2026-11-20')
            ->and($connection->refresh()->last_synced_at?->toDateTimeString())->not->toBeNull();

        $this->travelTo(CarbonImmutable::parse('2026-11-21 10:00', 'Europe/Rome'));
        $count = resolve(SyncBankConnection::class)->handle($connection->refresh());

        expect($count)->toBe(0)
            ->and($this->feed->requests()[1]['from']->toDateString())->toBe('2026-11-15')
            ->and($this->feed->requests()[1]['to']->toDateString())->toBe('2026-11-21')
            ->and(BankTransaction::query()->count())->toBe(1);
    });
});

test('an account without a uid is skipped', function (): void {
    insideBianchi(function (): void {
        [$connection] = bankSyncSetup(uid: null);

        resolve(SyncBankConnection::class)->handle($connection);

        expect($this->feed->calls())->toBe(0)
            ->and($connection->refresh()->status)->toBe(BankConnectionStatus::Active)
            ->and($connection->last_synced_at)->not->toBeNull();
    });
});

test('a successful read makes the bank active again and clears the last error', function (): void {
    insideBianchi(function (): void {
        [$connection] = bankSyncSetup(['status' => BankConnectionStatus::Error, 'last_error' => 'down']);
        $this->feed->gives([]);

        resolve(SyncBankConnection::class)->handle($connection);

        expect($connection->refresh()->status)->toBe(BankConnectionStatus::Active)
            ->and($connection->last_error)->toBeNull();
    });
});

test('a connection without a readable session is marked to reconnect and the bank is not called', function (): void {
    insideBianchi(function (): void {
        [$connection] = bankSyncSetup(session: false);

        resolve(SyncBankConnection::class)->handle($connection);

        expect($this->feed->calls())->toBe(0)
            ->and($connection->refresh()->status)->toBe(BankConnectionStatus::NeedsReconnect);
    });
});

test('a consent ended marks the bank to reconnect and the other bank is still read', function (): void {
    insideBianchi(function (): void {
        [$ended] = bankSyncSetup();
        [$other] = bankSyncSetup();
        $endedSession = $ended->sessionId();
        $entry = new BankEntry('ok', CarbonImmutable::parse('2026-11-19'), null, -5.0, 'EUR', 'Coffee', null, true);

        $feed = Mockery::mock(BankFeed::class);
        $feed->shouldReceive('transactions')->andReturnUsing(function (string $session) use ($endedSession, $entry): array {
            throw_if($session === $endedSession, BankConsentEnded::class, 'revoked');

            return [$entry];
        });
        app()->instance(BankFeed::class, $feed);

        $action = resolve(SyncBankConnection::class);

        expect($action->handle($ended))->toBe(0)
            ->and($action->handle($other))->toBe(1)
            ->and($ended->refresh()->status)->toBe(BankConnectionStatus::NeedsReconnect)
            ->and($other->refresh()->status)->toBe(BankConnectionStatus::Active)
            ->and($other->last_synced_at)->not->toBeNull();
    });
});

test('a limit reached changes nothing', function (): void {
    insideBianchi(function (): void {
        [$connection] = bankSyncSetup(['status' => BankConnectionStatus::Error, 'last_error' => 'before']);
        $this->feed->failsWith(new BankLimitReached('too many'));
        Exceptions::fake();

        $count = resolve(SyncBankConnection::class)->handle($connection);

        $connection->refresh();
        expect($count)->toBe(0)
            ->and($connection->status)->toBe(BankConnectionStatus::Error)
            ->and($connection->last_error)->toBe('before')
            ->and($connection->last_synced_at)->toBeNull();
        Exceptions::assertNothingReported();
    });
});

test('a failure is reported and saved as the last error', function (): void {
    insideBianchi(function (): void {
        [$connection] = bankSyncSetup();
        $this->feed->failsWith(new BankFeedFailed('The bank said no'));
        Exceptions::fake();

        $count = resolve(SyncBankConnection::class)->handle($connection);

        $connection->refresh();
        expect($count)->toBe(0)
            ->and($connection->status)->toBe(BankConnectionStatus::Error)
            ->and($connection->last_error)->toBe('The bank said no')
            ->and($connection->last_synced_at)->toBeNull();
        Exceptions::assertReported(BankFeedFailed::class);
    });
});
