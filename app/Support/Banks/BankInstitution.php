<?php

declare(strict_types=1);

namespace App\Support\Banks;

/**
 * A bank a person can connect.
 */
final readonly class BankInstitution
{
    /**
     * @param  string  $name  the name the provider knows it by
     * @param  string  $country  the ISO code of the country
     * @param  string|null  $logo  the address of the logo
     * @param  int  $maxConsentDays  the longest consent the bank allows, in days
     */
    public function __construct(
        public string $name,
        public string $country,
        public ?string $logo,
        public int $maxConsentDays,
    ) {}
}
