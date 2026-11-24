<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Banks\WatchBankConsents;
use App\Console\Concerns\RunsPerFamily;
use App\Jobs\SyncBank;
use App\Models\BankConnection;
use App\Models\Family;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * The read of the banks, three times a day (`routes/console.php`): queues one read for every working connection of
 * every family that has the link to the bank turned on. A bank to connect again is not read until the person does.
 */
#[Description('Queue the reading of the transactions of the connected banks')]
#[Signature('banks:sync {--family= : Only this family (its id)}')]
final class SyncBanks extends Command
{
    use RunsPerFamily;

    /**
     * Queues the reads, after the consents of each family were checked. A family that fails is reported and skipped, so it does not stop the others.
     *
     * @param  WatchBankConsents  $watch  Checks the consents of the family before its reads.
     */
    public function handle(WatchBankConsents $watch): int
    {
        if (! $this->hasValidFamilyOption()) {
            return self::FAILURE;
        }

        return $this->runPerFamily('Reads queued', static function (Family $family) use ($watch): int {
            try {
                $watch->handle(CarbonImmutable::now());
            } catch (Throwable $failure) {
                // The reads of the family go on even when the check of its consents fails
                report($failure);
            }

            $connections = BankConnection::query()->readable()->orderBy('id')->get(['id']);

            foreach ($connections as $connection) {
                dispatch(new SyncBank($connection->id, $family->id));
            }

            return $connections->count();
        }, static fn (Builder $families) => $families->bankFeedOn());
    }
}
