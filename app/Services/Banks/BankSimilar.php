<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Data\BankTransactionData;
use App\Enums\BankFilterAction;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Support\Banks\BankFilterDraft;
use App\Support\Banks\BankText;
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
     * @param  BankFees  $fees  which commission belongs to which debit
     */
    public function __construct(private BankGroups $groups, private BankFees $fees) {}

    /**
     * Flashes `similar` for the movement just sorted: `count` of the others like it, the first twenty as `rows`, the
     * `action` the filter would do (`pay`, `create` or `transfer`), the filter itself as data (`filter`, with the words
     * the movements share), why they are alike (`reason`: the mandate or the words, null without a name or words), whether
     * any of them has a commission (`fee`; each row carries its own), their amounts (`min`, `max`), days (`from`, `to`)
     * and `kind`, and the `url` of the form of the new filter.
     *
     * @param  BankTransaction  $transaction  the movement just sorted
     */
    public function offer(BankTransaction $transaction): void
    {
        $transaction = $transaction->fresh(['expense.plannedExpense', 'income.plannedIncome', 'financialAccount']);

        if (! $transaction instanceof BankTransaction) {
            return;
        }

        $siblings = $this->groups->siblings($transaction);

        if ($siblings->isEmpty()) {
            return;
        }

        $draft = BankFilterDraft::fromLink($transaction, Gate::allows('create', [BankFilter::class, BankFilterAction::Pay]), $siblings);

        if (! $draft->action instanceof BankFilterAction) {
            return;
        }

        $rows = $siblings->take(self::ROWS)->values();
        $fees = $this->fees->pairs($siblings);

        $amounts = $siblings->map(static fn (BankTransaction $row): float => round(abs((float) $row->amount), 2));
        $days = $siblings->map(static fn (BankTransaction $row): string => (string) ($row->booked_on ?? $row->value_on)?->toDateString());

        Inertia::flash('similar', [
            'count' => $siblings->count(),
            'kind' => $transaction->kind(),
            'filter' => $draft->toData()->toArray(),
            'reason' => $this->reason($transaction, $draft),
            'fee' => $fees !== [],
            'min' => (float) $amounts->min(),
            'max' => (float) $amounts->max(),
            'from' => (string) $days->min(),
            'to' => (string) $days->max(),
            'rows' => $rows->map(static fn (BankTransaction $row): BankTransactionData => BankTransactionData::fromTransaction($row, fee: $fees[$row->id] ?? null))->all(),
            'action' => $draft->action->value,
            'url' => route('budget.bank.filters.create', ['from' => $transaction->id], absolute: false),
        ]);
    }

    /**
     * Why the movements are alike: the mandate they share (with the name of the filter) or the words of the filter;
     * null when there is no name or no words to say, so the page writes no sentence with a hole in it.
     *
     * @param  BankTransaction  $transaction  the movement just sorted
     * @param  BankFilterDraft  $draft  the filter for the others
     * @return array{kind: 'mandate'|'words', name: string}|null
     */
    private function reason(BankTransaction $transaction, BankFilterDraft $draft): ?array
    {
        $mandate = BankText::mandate($transaction->text) !== null;
        $name = trim($mandate ? $draft->name : implode(' ', $draft->contains));

        if ($name === '') {
            return null;
        }

        return ['kind' => $mandate ? 'mandate' : 'words', 'name' => $name];
    }
}
