<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Support\Reminders\Expiries;
use Carbon\CarbonImmutable;

/**
 * Moves the cards whose date has passed to their expired status, in the current family. Runs before the reminders are
 * synced, which read the status.
 */
final class ExpireCards
{
    /**
     * One update per table, through the query builder: no observers run, since the sync of the reminders follows.
     *
     * @param  CarbonImmutable  $today  the day in Rome
     * @return int how many cards changed
     */
    public function handle(CarbonImmutable $today): int
    {
        $count = 0;

        foreach (Expiries::all() as $expiry) {
            $count += $expiry['model']::query()
                ->where('status', $expiry['from'])
                ->whereDate($expiry['column'], '<', $today->toDateString())
                ->toBase()
                ->update(['status' => $expiry['to']->value]);
        }

        return $count;
    }
}
