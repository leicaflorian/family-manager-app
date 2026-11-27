<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * What a bank filter does with the transactions it takes (`bank_filters.action`).
 */
enum BankFilterAction: string
{
    use HasLabel;

    /** Pays the due date of a planned expense or income. */
    case Pay = 'pay';
    /** Creates a new expense or income. */
    case Create = 'create';
    /** The transaction is a move between two accounts of the family. */
    case Transfer = 'transfer';
    /** Puts the transaction aside. */
    case Ignore = 'ignore';
}
