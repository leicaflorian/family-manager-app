<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Banks\SyncBankConnection;
use App\Models\BankConnection;
use App\Support\CurrentFamily;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;

/**
 * Reads the transactions of a bank connection, inside its family (the queue has nobody signed in). One read per
 * connection at a time, and one try: the next scheduled read starts again.
 */
#[Tries(1)]
#[UniqueFor(900)]
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
    public function handle(SyncBankConnection $sync): void
    {
        CurrentFamily::using($this->familyId, function () use ($sync): void {
            $connection = BankConnection::query()->find($this->connectionId);

            if ($connection instanceof BankConnection) {
                $sync->handle($connection);
            }
        });
    }
}
