<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Which number of the version a release raises.
 */
enum ReleaseBump: string
{
    use HasLabel;

    case Major = 'major';
    case Minor = 'minor';
    case Patch = 'patch';
}
