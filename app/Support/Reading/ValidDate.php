<?php

declare(strict_types=1);

namespace App\Support\Reading;

use Carbon\CarbonImmutable;

/**
 * The dates a model writes: a day the calendar does not have (30 February, month 13) counts as missing.
 */
final class ValidDate
{
    /**
     * The date, if it is a day of the calendar written `YYYY-MM-DD`.
     *
     * @param  string|null  $value  the date as the model wrote it
     * @return string|null the same date, or null when it is empty, not in that form or not a real day
     */
    public static function of(?string $value): ?string
    {
        if ($value === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1) {
            return null;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : null;
    }

    /**
     * The date as a Carbon day, if it is a valid one.
     *
     * @param  string|null  $value  the date as the model wrote it
     */
    public static function carbon(?string $value): ?CarbonImmutable
    {
        $valid = self::of($value);

        return $valid === null ? null : CarbonImmutable::parse($valid, 'Europe/Rome')->startOfDay();
    }
}
