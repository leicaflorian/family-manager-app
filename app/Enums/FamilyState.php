<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Where a family is in the eyes of the owner panel: working, suspended, or waiting to be deleted.
 */
enum FamilyState: string
{
    use HasLabel;

    case Active = 'active';
    case Suspended = 'suspended';
    case ToDelete = 'to_delete';
}
