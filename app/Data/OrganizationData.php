<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Organization;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An organization in *Organizations*: its types and the records that name it.
 */
#[TypeScript]
final class OrganizationData extends Data
{
    /**
     * @param  int  $id  the id
     * @param  string  $name  the name
     * @param  list<string>  $types  the values of its types, for the filter and the icon
     * @param  list<string>  $type_labels  the labels of its types
     * @param  string|null  $logo  the address of the logo, or null for the icon of its type
     * @param  list<string>  $records  the names of the records that name it
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array $types,
        public readonly array $type_labels,
        public readonly ?string $logo,
        public readonly array $records,
    ) {}

    /**
     * An organization of the list, with `logo` loaded.
     *
     * @param  Organization  $organization  the organization
     * @param  list<string>  $records  the names of its records, from `LinkedRecords::labelsByOrganization()`
     */
    public static function fromOrganization(Organization $organization, array $records): self
    {
        return new self($organization->id, $organization->name, $organization->typeValues(), $organization->typeLabels(), $organization->logoUrl(), $records);
    }
}
