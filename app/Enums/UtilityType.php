<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The type of a utility (`utilities.type`).
 * The values are those of Directus.
 */
enum UtilityType: string
{
    use HasLabel;

    case Electricity = 'electricity';
    case Gas = 'gas';
    case Water = 'water';
    case Internet = 'internet';
    case Phone = 'phone';
    case WasteTax = 'waste_tax';
    case TvLicense = 'tv_license';
}
