<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * How a bank transaction came to be linked to an expense or an income (`bank_transactions.matched_by`).
 */
enum BankMatchSource: string
{
    use HasLabel;

    /** Paid by a rule of a planned item. */
    case Rule = 'rule';
    /** A *Sì* on a proposal paid a due date. */
    case Proposed = 'proposed';
    /** The person said it belongs to a planned item (*È di una spesa fissa*). */
    case Assigned = 'assigned';
    /** The person made a new expense or income from it (*Crea la spesa*). */
    case Created = 'created';
    /** Linked to an expense that was already there. */
    case Duplicate = 'duplicate';
    /** The commission of a direct debit, joined to the expense of its debit. */
    case Fee = 'fee';
}
