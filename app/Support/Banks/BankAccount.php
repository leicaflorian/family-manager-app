<?php

declare(strict_types=1);

namespace App\Support\Banks;

/**
 * An account of a session with a bank.
 */
final readonly class BankAccount
{
    /**
     * @param  string  $uid  the id of the account inside the session, used to read its transactions
     * @param  string  $key  the stable hash of the account, the same in a new session
     * @param  string  $name  the name to show
     * @param  string|null  $lastFour  the last four digits of the IBAN
     * @param  string  $currency  the ISO code of the currency
     */
    public function __construct(
        public string $uid,
        public string $key,
        public string $name,
        public ?string $lastFour,
        public string $currency,
    ) {}
}
