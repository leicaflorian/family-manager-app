<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The status of a loan (`loans.status`).
 * The values are those of Directus.
 */
enum LoanStatus: string
{
    use HasLabel;

    case Active = 'active';
    case PaidOff = 'paid_off';
}
