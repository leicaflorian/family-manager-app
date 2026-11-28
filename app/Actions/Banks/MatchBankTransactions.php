<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Enums\BankFilterOutcome;
use App\Enums\BankMatchSource;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Services\Banks\BankFilters;
use App\Services\Banks\BankSplit;
use App\Support\Banks\BankSplitMatch;
use Throwable;

/**
 * Lets the bank filters work by themselves: on the new transactions of a read of the bank, and on the transactions
 * still to sort that a filter just saved takes. A transaction that two filters or more take is left to the person,
 * who sees their names on its row, unless they all pay and one planned item is the closest in amount
 * (`BankFilters::settling()`). Before that, a debit that pays the planned expenses of two filters or more together (a
 * bill of electricity and gas, `BankSplit`) pays them all. Runs in a request and in the queued read of a bank.
 */
final readonly class MatchBankTransactions
{
    public function __construct(private BankFilters $filters, private ApplyBankFilter $apply, private BankSplit $split, private SettleFromBank $settle) {}

    /**
     * Applies to every transaction the filter that settles it (`BankFilters::settling()`): the only one that takes
     * it, or among filters that all pay the one closest in amount; otherwise nothing happens. A debit those filters
     * pay together (`BankSplit`) is first settled as one payment of each of their planned expenses. A transaction that fails (also while its filters are read) is reported and left to
     * sort, and the others go on.
     *
     * @param  iterable<BankTransaction>  $transactions  The transactions, in the order to settle them.
     * @return int how many a filter settled (paid, created, linked, marked as a transfer or put aside)
     */
    public function handle(iterable $transactions): int
    {
        $settled = 0;

        foreach ($transactions as $transaction) {
            $done = $this->safely(function () use ($transaction): bool {
                if ($this->settleSplit($transaction)) {
                    return true;
                }

                $filter = $this->filters->settling($transaction);

                return $filter instanceof BankFilter && $this->apply->handle($transaction, $filter) !== BankFilterOutcome::None;
            });

            if ($done) {
                $settled++;
            }
        }

        return $settled;
    }

    /**
     * Applies a filter just saved to the transactions still to sort and not declined of the last days of a first read
     * (90) that it takes, the oldest first, so the due dates are paid in order. One that another filter takes too is
     * left to the person (unless this filter wins it, `BankFilters::settling()`), and the linked ones are never
     * touched. A debit that pays the planned expense of the filter together with others (`BankSplit`) pays them all.
     * A failure is reported and never leaves: the filter is already saved. Not to be called inside an open transaction: it reads the transactions with plain reads before
     * each one is locked, and inside a transaction those reads would fix the snapshot before the locks; the caller
     * commits the save of the filter first.
     *
     * @param  BankFilter  $filter  The filter, saved.
     * @return int how many it settled
     */
    public function forFilter(BankFilter $filter): int
    {
        $transactions = $this->safely(fn (): array => $this->filters->taken($filter->criteria(), config()->integer('bank_feed.first_days'))
            ->filter(BankFilters::toSort(...))
            ->sortBy([
                static fn (BankTransaction $a, BankTransaction $b): int => $a->booked_on?->toDateString() <=> $b->booked_on?->toDateString(),
                static fn (BankTransaction $a, BankTransaction $b): int => $a->id <=> $b->id,
            ])
            ->values()
            ->all(), []);

        $settled = 0;

        foreach ($transactions as $transaction) {
            $done = $this->safely(fn (): bool => $this->settleSplit($transaction, $filter)
                || ($this->filters->settling($transaction)?->is($filter) === true
                    && $this->apply->handle($transaction, $filter) !== BankFilterOutcome::None));

            if ($done) {
                $settled++;
            }
        }

        return $settled;
    }

    /**
     * Settles the transaction as one debit of more planned expenses together, when `BankSplit` finds one (and it pays
     * the planned expense of the filter given, when there is one). Only for a transaction still to sort.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @param  BankFilter|null  $filter  The filter just saved whose planned expense the split must pay, or null for any.
     * @return bool whether it paid or was linked
     */
    private function settleSplit(BankTransaction $transaction, ?BankFilter $filter = null): bool
    {
        $match = BankFilters::toSort($transaction) ? $this->split->find($transaction) : null;

        if (! $match instanceof BankSplitMatch
            || ($filter instanceof BankFilter && ($filter->planned_expense_id === null || ! in_array($filter->planned_expense_id, $match->plannedIds(), true)))) {
            return false;
        }

        return $this->settle->split($transaction, $match, BankMatchSource::Rule) !== [];
    }

    /**
     * Runs a piece of the matching; a failure (a database error, a filter that cannot be applied) is reported and gives
     * the fallback, so it never stops the read of a bank nor the save of a filter, and a transaction that failed stays to sort.
     *
     * @template T
     *
     * @param  callable(): T  $work  The work.
     * @param  T  $fallback  What a failure gives.
     * @return T
     */
    private function safely(callable $work, mixed $fallback = false): mixed
    {
        try {
            return $work();
        } catch (Throwable $failure) {
            report($failure);

            return $fallback;
        }
    }
}
