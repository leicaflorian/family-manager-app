<?php

declare(strict_types=1);

namespace App\Support\Banks;

use Illuminate\Support\Facades\Config;

/**
 * The hours of the day (Europe/Rome) the banks are read by themselves, from `bank_feed.sync_times`, as the pages say
 * them («alle 7, alle 13 e alle 19»).
 */
final class SyncHours
{
    /**
     * The hours, in the order of the config.
     *
     * @return list<int>
     */
    public static function all(): array
    {
        return array_map(static fn (mixed $time): int => (int) (is_string($time) ? $time : 0), array_values(Config::array('bank_feed.sync_times')));
    }
}
