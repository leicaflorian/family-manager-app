<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The status of a home (`homes.status`).
 * The values are those of Directus.
 */
enum HomeStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Archived = 'archived';
}
