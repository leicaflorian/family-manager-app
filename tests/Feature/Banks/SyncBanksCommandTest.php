<?php

declare(strict_types=1);

use App\Contracts\BankFeed;
use App\Enums\BankConnectionStatus;
use App\Jobs\SyncBank;
use App\Models\BankConnection;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Services\Banks\FakeBankFeed;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Support\Facades\Queue;

/**
 * A family with a connection in a status.
 *
 * @param  array<string, mixed>  $family  changes to the family
 */
function bankCommandConnection(array $family = ['bank_feed_enabled' => true], BankConnectionStatus $status = BankConnectionStatus::Active): BankConnection
{
    $family = Family::factory()->create($family);

    return BankConnection::factory()->create(['family_id' => $family->id, 'status' => $status]);
}

test('only the families with the feed on are synced', function (): void {
    Queue::fake();
    $on = bankCommandConnection();
    bankCommandConnection(['bank_feed_enabled' => false]);
    bankCommandConnection(['bank_feed_enabled' => true, 'suspended_at' => now()]);
    $failing = bankCommandConnection(status: BankConnectionStatus::Error);

    $this->artisan('banks:sync')->assertSuccessful();

    Queue::assertPushed(SyncBank::class, 2);
    Queue::assertPushed(SyncBank::class, fn (SyncBank $job): bool => $job->connectionId === $on->id && $job->familyId === $on->family_id);
    Queue::assertPushed(SyncBank::class, fn (SyncBank $job): bool => $job->connectionId === $failing->id);
});

test('a bank to reconnect is not queued', function (): void {
    Queue::fake();
    bankCommandConnection(status: BankConnectionStatus::NeedsReconnect);

    $this->artisan('banks:sync')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('the family option reads only that family', function (): void {
    Queue::fake();
    $one = bankCommandConnection();
    bankCommandConnection();

    $this->artisan('banks:sync', ['--family' => $one->family_id])->assertSuccessful();

    Queue::assertPushed(SyncBank::class, 1);
});

test('the job reads the bank inside its family', function (): void {
    $connection = bankCommandConnection();
    CurrentFamily::using($connection->family_id, function () use ($connection): void {
        $connection->writeSession('s', CarbonImmutable::now()->addDays(10));
        FinancialAccount::factory()->create(['family_id' => $connection->family_id, 'bank_connection_id' => $connection->id, 'bank_account_uid' => 'u']);
    });
    $feed = (new FakeBankFeed)->gives([]);
    app()->instance(BankFeed::class, $feed);

    dispatch_sync(new SyncBank($connection->id, $connection->family_id));

    expect($feed->calls())->toBe(1);
});

test('the job is unique per connection and waits long enough for a first read', function (): void {
    $job = new SyncBank(7, 1);

    expect($job->uniqueId())->toBe('7')
        ->and((new ReflectionClass($job))->getAttributes(Timeout::class)[0]->newInstance()->timeout)->toBe(80);
});

test('the job does nothing when the family, the feed or the connection changed since it was queued', function (): void {
    $feed = (new FakeBankFeed)->gives([]);
    app()->instance(BankFeed::class, $feed);
    $setup = function (array $family, BankConnectionStatus $status = BankConnectionStatus::Active): BankConnection {
        $connection = bankCommandConnection($family, $status);
        CurrentFamily::using($connection->family_id, function () use ($connection): void {
            $connection->writeSession('s', CarbonImmutable::now()->addDays(10));
            FinancialAccount::factory()->create(['family_id' => $connection->family_id, 'bank_connection_id' => $connection->id, 'bank_account_uid' => 'u']);
        });

        return $connection;
    };

    foreach ([
        $setup(['bank_feed_enabled' => false]),
        $setup(['bank_feed_enabled' => true, 'suspended_at' => now()]),
        $setup(['bank_feed_enabled' => true], BankConnectionStatus::NeedsReconnect),
    ] as $connection) {
        dispatch_sync(new SyncBank($connection->id, $connection->family_id));
    }

    expect($feed->calls())->toBe(0);
});
