<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The types of an organization (`organizations.types`, more than one each).
 * The values are those of Directus.
 */
enum OrganizationType: string
{
    use HasLabel;

    case Bank = 'bank';
    case Insurance = 'insurance';
    case UtilityProvider = 'utility_provider';
    case Employer = 'employer';
    case PublicOffice = 'public_office';
    case School = 'school';
    case Healthcare = 'healthcare';
    case Shop = 'shop';
    case Other = 'other';
}
