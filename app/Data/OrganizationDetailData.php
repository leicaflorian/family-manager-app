<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Organization;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An organization as *An organization* shows it and *Edit an organization* changes it.
 */
#[TypeScript]
final class OrganizationDetailData extends Data
{
    /**
     * @param  int  $id  the id
     * @param  string  $name  the name
     * @param  list<string>  $types  the values of its types
     * @param  list<string>  $type_labels  the labels of its types
     * @param  string|null  $logo  the address of the logo, or null for the icon of its type
     * @param  string|null  $email  the email
     * @param  string|null  $phone  the phone number
     * @param  string|null  $website  the website, as it was written
     * @param  string|null  $notes  the notes, for example the customer code
     * @param  bool  $can_edit  whoever is looking can change it
     * @param  bool  $can_delete  whoever is looking can delete it
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array $types,
        public readonly array $type_labels,
        public readonly ?string $logo,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $website,
        public readonly ?string $notes,
        public readonly bool $can_edit,
        public readonly bool $can_delete,
    ) {}

    /**
     * The data of an organization, for whoever is looking.
     *
     * @param  Organization  $organization  the organization
     * @param  User  $viewer  whoever is looking
     */
    public static function fromOrganization(Organization $organization, User $viewer): self
    {
        return new self(
            id: $organization->id,
            name: $organization->name,
            types: $organization->typeValues(),
            type_labels: $organization->typeLabels(),
            logo: $organization->logoUrl(),
            email: $organization->email,
            phone: $organization->phone,
            website: $organization->website,
            notes: $organization->notes,
            can_edit: $viewer->can('update', $organization),
            can_delete: $viewer->can('delete', $organization),
        );
    }
}
