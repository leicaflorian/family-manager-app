<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The status of a financial account (`financial_accounts.status`).
 * The values are those of Directus.
 */
enum FinancialAccountStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Closed = 'closed';
}
