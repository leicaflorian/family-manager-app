<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The status of a document (`documents.status`).
 * The values are those of Directus.
 */
enum DocumentStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Archived = 'archived';
    case Expired = 'expired';
}
