<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * What an exception to a planned expense or income changes.
 * The values are those of Directus.
 */
enum ExceptionType: string
{
    use HasLabel;

    case Skip = 'skip';
    case AmountOverride = 'amount_override';
    case DateOverride = 'date_override';
}
