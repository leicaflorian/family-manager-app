<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Number;

/**
 * Amounts compared as whole cents, so that `30.10 - 0.10` and `30` are equal whatever the float, and written for
 * the person.
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

    /**
     * The amount as the person reads it in both languages: `84,20 €`.
     *
     * @param  float  $amount  the amount in euro
     */
    public static function euro(float $amount): string
    {
        return Number::currency($amount, 'EUR', 'it') ?: number_format($amount, 2, ',', '.').' €';
    }
}
