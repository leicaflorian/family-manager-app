<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The status of an employment (`employments.status`).
 * The values are those of Directus.
 */
enum EmploymentStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Ended = 'ended';
}
