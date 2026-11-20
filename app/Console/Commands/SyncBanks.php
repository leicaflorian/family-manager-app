<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Banks\WatchBankConsents;
use App\Enums\BankConnectionStatus;
use App\Jobs\SyncBank;
use App\Models\BankConnection;
use App\Models\Family;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * The read of the banks, three times a day (`routes/console.php`): queues one read for every working connection of
 * every family that has the link to the bank turned on. A bank to connect again is not read until the person does.
 */
#[Description('Queue the reading of the transactions of the connected banks')]
#[Signature('banks:sync {--family= : Only this family (its id)}')]
final class SyncBanks extends Command
{
    /**
     * Queues the reads, after the consents of each family were checked. A family that fails is reported and skipped, so it does not stop the others.
     */
    public function handle(WatchBankConsents $watch): int
    {
        $option = $this->option('family');
        $only = is_scalar($option) ? (string) $option : '';

        if ($only !== '' && (! ctype_digit($only) || (int) $only < 1)) {
            $this->error('--family must be the id of a family.');

            return self::FAILURE;
        }

        $families = Family::query()->notBlocked()->where('bank_feed_enabled', true)
            ->when($only !== '', fn ($query) => $query->whereKey((int) $only))
            ->orderBy('id')->get(['id']);

        $queued = 0;
        $failed = 0;

        foreach ($families as $family) {
            try {
                $queued += CurrentFamily::using($family->id, function () use ($family, $watch): int {
                    $watch->handle(CarbonImmutable::now());

                    $connections = BankConnection::query()
                        ->whereIn('status', [BankConnectionStatus::Active, BankConnectionStatus::Error])
                        ->orderBy('id')->get(['id']);

                    foreach ($connections as $connection) {
                        dispatch(new SyncBank($connection->id, $family->id));
                    }

                    return $connections->count();
                });
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $this->info("Reads queued: {$queued}");

        if ($failed > 0) {
            $this->error("Families that failed: {$failed}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
