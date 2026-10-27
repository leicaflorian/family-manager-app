<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The status of a belonging (`belongings.status`).
 * The values are those of Directus.
 */
enum BelongingStatus: string
{
    use HasLabel;

    case InUse = 'in_use';
    case Disposed = 'disposed';
}
