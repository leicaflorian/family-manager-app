<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Enums\BankFilterOutcome;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Services\Banks\BankFilters;
use Throwable;

/**
 * Lets the bank filters work by themselves: on the new transactions of a read of the bank, and on the transactions
 * still to sort that a filter just saved takes. A transaction that two filters or more take is left to the person,
 * who sees their names on its row. Runs in a request and in the queued read of a bank.
 */
final readonly class MatchBankTransactions
{
    public function __construct(private BankFilters $filters, private ApplyBankFilter $apply) {}

    /**
     * Applies to every transaction the filter that takes it, when exactly one does (`BankFilters::matching()`); with
     * two or more nothing happens. A transaction that fails (also while its filters are read) is reported and left to
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
                $filters = $this->filters->matching($transaction);
                $filter = $filters->count() === 1 ? $filters->first() : null;

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
     * left to the person, and the linked ones are never touched. A failure is reported and never leaves: the filter is
     * already saved. Not to be called inside an open transaction: it reads the transactions with plain reads before
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
            $done = $this->safely(fn (): bool => $this->filters->matching($transaction)->every(static fn (BankFilter $other): bool => $other->is($filter))
                && $this->apply->handle($transaction, $filter) !== BankFilterOutcome::None);

            if ($done) {
                $settled++;
            }
        }

        return $settled;
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
