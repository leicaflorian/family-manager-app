<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * How often a planned expense or income repeats.
 * The values are those of Directus.
 */
enum IntervalUnit: string
{
    use HasLabel;

    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
}
