<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The type of a filing (`filings.type`).
 * The values are those of Directus.
 */
enum FilingType: string
{
    use HasLabel;

    case TaxReturn = 'tax_return';
    case Isee = 'isee';
    case Benefit = 'benefit';
    case ParentalLeave = 'parental_leave';
    case Municipal = 'municipal';
    case Other = 'other';
}
