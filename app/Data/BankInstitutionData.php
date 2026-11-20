<?php

declare(strict_types=1);

namespace App\Data;

use App\Support\Banks\BankInstitution;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A bank the person can choose in *Link a bank*.
 */
#[TypeScript]
final class BankInstitutionData extends Data
{
    /**
     * @param  string  $name  the name the provider knows the bank by
     * @param  string  $country  the ISO code of the country
     * @param  string|null  $logo  the address of the logo; null without one, the page draws the initials
     */
    public function __construct(
        public readonly string $name,
        public readonly string $country,
        public readonly ?string $logo,
    ) {}

    /**
     * The data of a bank of the provider.
     *
     * @param  BankInstitution  $institution  the bank
     */
    public static function fromInstitution(BankInstitution $institution): self
    {
        return new self($institution->name, $institution->country, $institution->logo);
    }
}
