<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The status of a utility (`utilities.status`).
 * The values are those of Directus.
 */
enum UtilityStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Terminated = 'terminated';
}
