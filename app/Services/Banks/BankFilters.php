<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Enums\BankTransactionStatus;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Support\Banks\BankFilterCriteria;
use App\Support\Banks\BankFilterMatch;
use App\Support\Banks\BankText;
use App\Support\Banks\RuleAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Which bank filters take a transaction, and which transactions the criteria of a filter take. The filters of the
 * family are read once per instance and kept in memory, so a bank read of many transactions asks the database once.
 * Nothing here applies a filter, and nothing throws for a filter whose planned item, category or account is gone:
 * only the criteria are looked at.
 */
final class BankFilters
{
    /** The most transactions read from the database for one question, as `BankMatchPreview` does. */
    private const int LIMIT = 2000;

    /**
     * The filters of the current family, loaded on first use.
     *
     * @var Collection<int, BankFilter>|null
     */
    private ?Collection $filters = null;

    /**
     * The filters that take the transaction, oldest first. A filter whose criteria are not safe never takes one, and
     * neither does a transaction that is not booked or not in euros. Whether it is already linked, ignored or a transfer
     * is not said here: whoever applies a filter checks `unsorted()` (`taken()` lists linked ones on purpose, to show
     * what a filter already did).
     *
     * @param  BankTransaction  $transaction  the transaction
     * @return Collection<int, BankFilter>
     */
    public function matching(BankTransaction $transaction): Collection
    {
        if ($transaction->status !== BankTransactionStatus::Booked || $transaction->currency !== 'EUR') {
            return new Collection;
        }

        return $this->all()
            ->filter(static fn (BankFilter $filter): bool => BankFilterMatch::matches($filter->criteria(), $transaction))
            ->values();
    }

    /**
     * The names of the filters that take a transaction, when two or more do: none of them settles it, and its row
     * says which ones. Null for one filter or none, and for a transaction no filter can settle: not booked, not in
     * euros, or a commission (it joins the expense of its debit by itself).
     *
     * @param  BankTransaction  $transaction  the transaction
     * @return list<string>|null
     */
    public function namesWhenShared(BankTransaction $transaction): ?array
    {
        if (BankText::isFee($transaction->text)) {
            return null;
        }

        $taking = $this->matching($transaction);

        return $taking->count() >= 2 ? array_values($taking->map(static fn (BankFilter $filter): string => $filter->name)->all()) : null;
    }

    /**
     * The booked euro transactions of the last days that the criteria take, linked ones included, newest first.
     *
     * @param  BankFilterCriteria  $criteria  what the filter takes
     * @param  int  $days  how many days back to look
     * @param  int|null  $except  the id of a transaction to leave out (the one the person says is not of the group)
     * @return Collection<int, BankTransaction>
     */
    public function taken(BankFilterCriteria $criteria, int $days = 90, ?int $except = null): Collection
    {
        if (! $criteria->isSafe()) {
            return new Collection;
        }

        $account = RuleAccount::of($criteria->accountId);

        return BankTransaction::query()
            ->with('financialAccount')
            ->where('status', BankTransactionStatus::Booked)
            ->where('currency', 'EUR')
            ->where('booked_on', '>=', CarbonImmutable::today('Europe/Rome')->subDays($days))
            ->where('amount', $criteria->direction === 'income' ? '>' : '<', 0)
            ->when($account !== null, static fn ($query) => $query->where('financial_account_id', $account))
            ->when($except !== null, static fn ($query) => $query->whereKeyNot($except))
            ->latest('booked_on')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->filter(static fn (BankTransaction $transaction): bool => BankFilterMatch::matches($criteria, $transaction))
            ->values();
    }

    /**
     * The other filter that already takes a transaction to sort: the oldest filter, but the one left out, that takes
     * it. A filter that would take it too would make two filters on one movement, which neither settles. Null when the
     * transaction is not to sort (linked, put aside, a transfer, declined by *It was not this one*, a commission: no
     * filter would touch it) or no other filter takes it.
     *
     * @param  BankTransaction  $transaction  the transaction
     * @param  int|null  $except  the id of the filter being changed, which never conflicts with itself
     */
    public function otherTaking(BankTransaction $transaction, ?int $except = null): ?BankFilter
    {
        if (! self::toSort($transaction)) {
            return null;
        }

        return $this->matching($transaction)->first(static fn (BankFilter $filter): bool => $filter->id !== $except);
    }

    /**
     * The conflicts of criteria with the other filters: each transaction to sort of the last days of a first read that
     * the criteria take and another filter takes already, with that filter, by the id of the transaction.
     *
     * @param  BankFilterCriteria  $criteria  what the filter takes
     * @param  int|null  $except  the id of the filter being changed
     * @return Collection<int, BankFilter>
     */
    public function conflicts(BankFilterCriteria $criteria, ?int $except = null): Collection
    {
        $conflicts = new Collection;

        foreach ($this->taken($criteria, config()->integer('bank_feed.first_days')) as $transaction) {
            $other = $this->otherTaking($transaction, $except);

            if ($other instanceof BankFilter) {
                $conflicts->put($transaction->id, $other);
            }
        }

        return $conflicts;
    }

    /**
     * Whether a filter could still settle the transaction: not linked, not put aside, not a transfer, not declined by
     * *It was not this one* and not a commission (a commission joins the expense of its debit by itself).
     *
     * @param  BankTransaction  $transaction  the transaction
     */
    public static function toSort(BankTransaction $transaction): bool
    {
        return $transaction->expense_id === null && $transaction->income_id === null && $transaction->ignored_at === null
            && $transaction->transfer_account_id === null && $transaction->declined_at === null && ! BankText::isFee($transaction->text);
    }

    /**
     * Every filter of the family, from the database the first time only.
     *
     * @return Collection<int, BankFilter>
     */
    private function all(): Collection
    {
        return $this->filters ??= BankFilter::query()->orderBy('id')->get();
    }
}
