<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Amounts compared as whole cents, so that `30.10 - 0.10` and `30` are equal whatever the float.
 */
final class Money
{
    /**
     * The value in cents, rounded; null and empty count as zero.
     *
     * @param  mixed  $value  A number or a decimal string (as the database gives it), or null.
     */
    public static function cents(mixed $value): int
    {
        return is_numeric($value) ? (int) round((float) $value * 100) : 0;
    }
}
