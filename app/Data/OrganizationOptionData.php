<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Organization;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An organization as a choice of a menu: the value and the name, with its types and its logo, so the field shows
 * its mark (*Who it is from* of the document form).
 */
#[TypeScript]
final class OrganizationOptionData extends Data
{
    /**
     * @param  string  $value  the id that is sent
     * @param  string  $label  the name
     * @param  string|null  $help  always empty: the menus read it as the sentence below
     * @param  list<string>  $types  the values of its types, for the icon
     * @param  string|null  $logo  the address of its logo
     */
    public function __construct(
        public readonly string $value,
        public readonly string $label,
        public readonly ?string $help,
        public readonly array $types,
        public readonly ?string $logo,
    ) {}

    /**
     * The choice of an organization; its logo should be loaded.
     *
     * @param  Organization  $organization  the organization
     */
    public static function fromModel(Organization $organization): self
    {
        return new self((string) $organization->id, $organization->name, null, $organization->typeValues(), $organization->logoUrl());
    }
}
