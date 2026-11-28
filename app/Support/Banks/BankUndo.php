<?php

declare(strict_types=1);

namespace App\Support\Banks;

use App\Enums\BankMatchSource;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use Illuminate\Support\Facades\Gate;

/**
 * What *It was not this one* does for a transaction linked to a movement, and whether the person may do it: one rule
 * for the line of the movement page and for the list *All*.
 */
final class BankUndo
{
    /** How a link came for the payments the bank recorded: undoing them deletes the movement. */
    public const array RECORDED = [BankMatchSource::Rule, BankMatchSource::Proposed, BankMatchSource::Assigned];

    /**
     * What taking the link back does: `delete` for a payment the bank recorded (who can delete the movement may do it),
     * `detach` for a link to a movement that was already there (who can change the transaction may do it); null when
     * it cannot be undone from here or the person may not.
     *
     * @param  BankTransaction  $transaction  the transaction, linked to the movement
     * @param  Expense|Income  $movement  the movement it is linked to
     * @return 'delete'|'detach'|null
     */
    public static function of(BankTransaction $transaction, Expense|Income $movement): ?string
    {
        return match (true) {
            in_array($transaction->matched_by, self::RECORDED, true) => Gate::allows('delete', $movement) ? 'delete' : null,
            $transaction->matched_by === BankMatchSource::Duplicate => Gate::allows('update', $transaction) ? 'detach' : null,
            default => null,
        };
    }
}
