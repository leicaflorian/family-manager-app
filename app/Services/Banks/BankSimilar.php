<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Data\BankTransactionData;
use App\Enums\BankFilterAction;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Support\Banks\BankFilterDraft;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * After the person sorts by hand one movement of a group, tells the page how many movements like it are still to sort,
 * and where the filter for them is made (the flash `similar`). It says nothing for a lone movement, and nothing when
 * the filter that would take them cannot be made by who sorted it (a due date paid, by a `member`).
 */
final readonly class BankSimilar
{
    /** How many of the other movements the flash carries as rows (the sheet shows five and the rest on tap); it says the count of all. */
    private const int ROWS = 20;

    /**
     * @param  BankGroups  $groups  the groups of the movements still to sort
     */
    public function __construct(private BankGroups $groups) {}

    /**
     * Flashes `similar` for the movement just sorted: `count` of the others like it, the first twenty as `rows`, the
     * `action` the filter would do (`pay`, `create` or `transfer`) and the `url` of the form of the new filter.
     *
     * @param  BankTransaction  $transaction  the movement just sorted
     */
    public function offer(BankTransaction $transaction): void
    {
        $transaction = $transaction->fresh(['expense.plannedExpense', 'income.plannedIncome', 'financialAccount']);

        if (! $transaction instanceof BankTransaction) {
            return;
        }

        $draft = BankFilterDraft::fromLink($transaction, Gate::allows('create', [BankFilter::class, BankFilterAction::Pay]));

        if (! $draft->action instanceof BankFilterAction) {
            return;
        }

        $siblings = $this->groups->siblings($transaction);

        if ($siblings->isEmpty()) {
            return;
        }

        Inertia::flash('similar', [
            'count' => $siblings->count(),
            'rows' => $siblings->take(self::ROWS)->map(static fn (BankTransaction $row): BankTransactionData => BankTransactionData::fromTransaction($row))->values()->all(),
            'action' => $draft->action->value,
            'url' => route('budget.bank.filters.create', ['from' => $transaction->id], absolute: false),
        ]);
    }
}
