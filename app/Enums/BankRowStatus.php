<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * What a bank transaction is in the list *All* of *From the bank*, worked out from its columns.
 */
enum BankRowStatus: string
{
    use HasLabel;

    /** Booked, in no expense or income and not put aside. */
    case Unsorted = 'unsorted';
    /** Linked to an expense or an income. */
    case Linked = 'linked';
    /** A fee the bank charged, added to the expense of its charge. */
    case Fee = 'fee';
    /** A transfer between the family's own accounts. */
    case Transfer = 'transfer';
    /** Put aside by the person. */
    case Ignored = 'ignored';
    /** Not booked by the bank yet. */
    case Pending = 'pending';
}
