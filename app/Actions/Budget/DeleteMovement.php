<?php

declare(strict_types=1);

namespace App\Actions\Budget;

use App\Models\Expense;
use App\Models\Income;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an expense or an income. Its documents stay: only their link to it goes.
 */
final readonly class DeleteMovement
{
    /**
     * Removes the links to the documents and the movement, in one transaction.
     *
     * @param  Expense|Income  $movement  the movement to delete
     */
    public function handle(Expense|Income $movement): void
    {
        DB::transaction(function () use ($movement): void {
            $movement->documents()->detach();
            $movement->delete();
        });
    }
}
