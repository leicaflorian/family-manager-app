<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * What a bank filter did with a transaction (`ApplyBankFilter`).
 */
enum BankFilterOutcome: string
{
    use HasLabel;

    /** It paid the due date of the planned item of the filter. */
    case Paid = 'paid';
    /** It created a new expense or income. */
    case Created = 'created';
    /** It linked the transaction to an expense or income already recorded. */
    case Linked = 'linked';
    /** It marked the transaction as a transfer between two accounts of the family. */
    case Transferred = 'transferred';
    /** It put the transaction aside. */
    case Ignored = 'ignored';
    /** It did nothing: the transaction stays to sort (or was not to sort any more). */
    case None = 'none';
}
