<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The type of an insurance policy (`insurance_policies.type`).
 * The values are those of Directus.
 */
enum InsurancePolicyType: string
{
    use HasLabel;

    case Car = 'car';
    case Life = 'life';
    case Home = 'home';
    case Health = 'health';
    case Accident = 'accident';
    case Fund = 'fund';
    case Other = 'other';
}
