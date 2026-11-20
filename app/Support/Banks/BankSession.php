<?php

declare(strict_types=1);

namespace App\Support\Banks;

use Carbon\CarbonImmutable;

/**
 * The consent of a person to read a bank, with the accounts it covers.
 */
final readonly class BankSession
{
    /**
     * @param  string  $id  the id of the session at the provider
     * @param  CarbonImmutable  $validUntil  the end of the consent
     * @param  string  $institution  the name of the bank
     * @param  list<BankAccount>  $accounts  the accounts the person shared
     */
    public function __construct(
        public string $id,
        public CarbonImmutable $validUntil,
        public string $institution,
        public array $accounts,
    ) {}
}
