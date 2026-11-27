<?php

declare(strict_types=1);

namespace App\Actions\Budget;

use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an expense or an income. Its documents stay: only their link to it goes. The expense a bank debit is linked
 * to ends the split that debit paid together: the other expenses stay, free of the debit.
 */
final readonly class DeleteMovement
{
    /**
     * Removes the links to the documents and the movement, in one transaction. The bank transaction linked to an
     * expense is locked first and its split released, so a settling of the same debit waits for it.
     *
     * @param  Expense|Income  $movement  the movement to delete
     */
    public function handle(Expense|Income $movement): void
    {
        DB::transaction(function () use ($movement): void {
            if ($movement instanceof Expense) {
                BankTransaction::query()->where('expense_id', $movement->id)->lockForUpdate()->first()?->releaseSplit();
            }

            $movement->documents()->detach();
            $movement->delete();
        });
    }
}
