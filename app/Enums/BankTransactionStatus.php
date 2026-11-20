<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Whether the bank has booked a transaction or it is still pending (`bank_transactions.status`).
 */
enum BankTransactionStatus: string
{
    use HasLabel;

    case Booked = 'booked';
    case Pending = 'pending';
}
