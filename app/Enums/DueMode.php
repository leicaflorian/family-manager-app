<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * What a bill to pay does with its due date: makes a new planned expense, joins one that is already there, or adds
 * an exception to the due date of a commitment that repeats.
 */
enum DueMode: string
{
    use HasLabel;

    case New = 'new';
    case Existing = 'existing';
    case Exception = 'exception';
}
