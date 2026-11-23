<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Banks\SyncBankConnection;
use App\Enums\Feature;
use App\Models\BankConnection;
use App\Models\Family;
use App\Services\FamilyFeatures;
use App\Support\CurrentFamily;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;

/**
 * Reads the transactions of a bank connection, inside its family (the queue has nobody signed in). One read per
 * connection at a time, and one try: the next scheduled read starts again.
 */
#[Tries(1)]
#[UniqueFor(900)]
// Shorter than `retry_after` (90 s) of the database queue, or a second worker would take the running job
#[Timeout(80)]
final class SyncBank implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * @param  int  $connectionId  the connection to read
     * @param  int  $familyId  the family of the connection: the queue has nobody signed in
     */
    public function __construct(public readonly int $connectionId, public readonly int $familyId) {}

    /**
     * One job per connection at a time.
     */
    public function uniqueId(): string
    {
        return (string) $this->connectionId;
    }

    /**
     * Reads the connection, if it is still there.
     */
    public function handle(SyncBankConnection $sync, FamilyFeatures $features): void
    {
        CurrentFamily::using($this->familyId, function () use ($sync, $features): void {
            $connection = BankConnection::query()->find($this->connectionId);

            // The family may have been blocked or turned off, or the bank disconnected, since the read was queued
            $family = Family::query()->find($this->familyId);
            $readable = $family instanceof Family && $family->hasBankFeedOn() && $features->for($family, Feature::BankFeed);

            if ($readable && $connection instanceof BankConnection && $connection->isReadable()) {
                $sync->handle($connection);
            }
        });
    }
}
