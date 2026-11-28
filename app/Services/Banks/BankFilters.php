<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Enums\BankFilterAction;
use App\Enums\BankTransactionStatus;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\Banks\BankFilterCriteria;
use App\Support\Banks\BankFilterMatch;
use App\Support\Banks\BankText;
use App\Support\Banks\RuleAccount;
use App\Support\Money;
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
    /** The most transactions read from the database for one question. */
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
     * The filter that settles a transaction: the only one that takes it. When two or more take it and all of them pay
     * a planned item (two old rules on the same lender became two filters), the one whose planned item, still payable
     * on the day, is closest in amount wins, as the old rules did; two at the same distance cannot be told apart. Any
     * other pair of filters settles nothing, and the row names them. The planned items are plain reads: this runs
     * before `ApplyBankFilter` takes its locks, never inside them.
     *
     * @param  BankTransaction  $transaction  the transaction
     */
    public function settling(BankTransaction $transaction): ?BankFilter
    {
        $taking = $this->matching($transaction);

        if ($taking->count() <= 1) {
            return $taking->first();
        }

        if ($taking->contains(static fn (BankFilter $filter): bool => $filter->action !== BankFilterAction::Pay)) {
            return null;
        }

        $cents = abs(Money::cents($transaction->amount));
        $ranked = [];

        foreach ($taking as $filter) {
            $planned = $filter->loadMissing(['plannedExpense', 'plannedIncome'])->plannedExpense ?? $filter->plannedIncome;

            if ($planned !== null && self::payable($planned, $transaction)) {
                $ranked[] = ['distance' => abs($this->plannedCents($planned) - $cents), 'filter' => $filter];
            }
        }

        // A stable sort: at the same distance the oldest filter comes first, and the tie below leaves both
        usort($ranked, static fn (array $a, array $b): int => $a['distance'] <=> $b['distance']);

        if ($ranked === [] || (isset($ranked[1]) && $ranked[1]['distance'] === $ranked[0]['distance'])) {
            return null;
        }

        return $ranked[0]['filter'];
    }

    /**
     * Whether the planned item of a filter that pays can be paid with the transaction: it is of the same direction,
     * and did not end before the day of the transaction.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item
     * @param  BankTransaction  $transaction  the transaction
     */
    public static function payable(PlannedExpense|PlannedIncome $planned, BankTransaction $transaction): bool
    {
        $day = (string) $transaction->booked_on?->toDateString();

        return ($planned instanceof PlannedExpense) === ($transaction->kind() === 'expense')
            && ($planned->end_date === null || $planned->end_date->toDateString() >= $day);
    }

    /**
     * The names of the filters that take a transaction, when two or more do and none of them settles it
     * (`settling()`): its row says which ones. Null for one filter or none, for two that pay told apart by the amount,
     * and for a transaction no filter can settle: not booked, not in euros, or a commission (it joins the expense of
     * its debit by itself).
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

        return $taking->count() >= 2 && ! $this->settling($transaction) instanceof BankFilter
            ? array_values($taking->map(static fn (BankFilter $filter): string => $filter->name)->all())
            : null;
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
     * The amount of a planned item in cents, its fee included, as the old rules compared it with the bank.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item
     */
    private function plannedCents(PlannedExpense|PlannedIncome $planned): int
    {
        return Money::cents($planned->amount) + ($planned instanceof PlannedExpense ? Money::cents($planned->fee_amount) : 0);
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
