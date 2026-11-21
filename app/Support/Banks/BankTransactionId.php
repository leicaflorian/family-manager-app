<?php

declare(strict_types=1);

namespace App\Support\Banks;

/**
 * The id of a bank transaction written in an address (`?bank=`, `?assign=`, `?transaction=`): only digits, at most
 * eighteen so it always fits an integer, otherwise none.
 */
final class BankTransactionId
{
    /**
     * The id, or null when the value is not one.
     *
     * @param  mixed  $value  the value of the address
     */
    public static function parse(mixed $value): ?int
    {
        return is_string($value) && preg_match('/^\d{1,18}$/', $value) === 1 ? (int) $value : null;
    }
}
