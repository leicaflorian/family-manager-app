<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Enums\BankTransactionStatus;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Support\Banks\BankFilterCriteria;
use App\Support\Banks\BankFilterMatch;
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
     * Every filter of the family, from the database the first time only.
     *
     * @return Collection<int, BankFilter>
     */
    private function all(): Collection
    {
        return $this->filters ??= BankFilter::query()->orderBy('id')->get();
    }
}
