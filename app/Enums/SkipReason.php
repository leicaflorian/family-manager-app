<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Why an attachment of an incoming mail was not saved.
 */
enum SkipReason: string
{
    use HasLabel;

    case TooSmall = 'too_small';
    case TooLarge = 'too_large';
    case NotADocument = 'not_a_document';
    case Duplicate = 'duplicate';
}
