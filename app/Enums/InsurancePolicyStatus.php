<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The status of an insurance policy (`insurance_policies.status`).
 * The values are those of Directus.
 */
enum InsurancePolicyStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
