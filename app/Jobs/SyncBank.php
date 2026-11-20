<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Reads the transactions of a bank connection. A shell for now: the reading arrives with the sync of the banks.
 */
final class SyncBank implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int  $connectionId  the connection to read
     * @param  int  $familyId  the family of the connection: the queue has nobody signed in
     */
    public function __construct(public readonly int $connectionId, public readonly int $familyId) {}

    /**
     * Only notes that the read was asked for: the reading itself arrives with the sync of the banks.
     */
    public function handle(): void
    {
        Log::debug('Bank read asked, not done yet.', ['connection' => $this->connectionId]);
    }
}
