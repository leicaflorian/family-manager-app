<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The groups the results of the global search are split into, in the order they are shown.
 */
enum SearchGroup: string
{
    use HasLabel;

    case Cards = 'cards';
    case Documents = 'documents';
    case Movements = 'movements';
    case Dues = 'dues';
    case Contacts = 'contacts';
}
