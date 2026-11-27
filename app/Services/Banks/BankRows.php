<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Enums\BankMatchSource;
use App\Enums\BankRowStatus;
use App\Enums\BankTransactionStatus;
use App\Enums\FinancialAccountStatus;
use App\Http\Requests\Banks\BankListRequest;
use App\Models\BankTransaction;
use App\Models\FinancialAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The list *All* of *From the bank*: every transaction the bank gave, filtered, in blocks, with the total of what the
 * filters find.
 */
final class BankRows
{
    /** How many rows a block of the list has. */
    public const int PER_PAGE = 50;

    /**
     * A block of the list, newest first, with the links to the expense or income loaded.
     *
     * @param  BankListRequest  $filters  the filters chosen
     * @param  int  $page  the number of the block, from 1
     * @return array{rows: Collection<int, BankTransaction>, more: bool}
     */
    public function page(BankListRequest $filters, int $page): array
    {
        $rows = $this->filtered($filters)
            ->with(['financialAccount', 'transferAccount', 'expense', 'income.organization'])
            ->orderByRaw('coalesce(booked_on, value_on) desc')
            ->orderByDesc('id')
            ->skip((max(1, $page) - 1) * self::PER_PAGE)
            ->take(self::PER_PAGE + 1)
            ->get();

        return ['rows' => $rows->take(self::PER_PAGE)->values(), 'more' => $rows->count() > self::PER_PAGE];
    }

    /**
     * How many transactions the filters find and what they add up to (the sign is kept: payments are negative).
     *
     * @param  BankListRequest  $filters  the filters chosen
     * @return array{count: int, sum: float}
     */
    public function total(BankListRequest $filters): array
    {
        $row = $this->filtered($filters)->toBase()->selectRaw('count(*) as total, coalesce(sum(amount), 0) as amount')->first();

        return ['count' => (int) ($row->total ?? 0), 'sum' => round((float) ($row->amount ?? 0), 2)];
    }

    /**
     * The accounts that have transactions, by name.
     *
     * @return list<array{id: int, name: string}>
     */
    public function accounts(): array
    {
        $accounts = FinancialAccount::query()
            ->whereIn('id', BankTransaction::query()->select('financial_account_id'))
            ->orderBy('name')
            ->get()
            ->map(static fn (FinancialAccount $account): array => [
                'id' => $account->id,
                'name' => $account->last_four_digits === null ? $account->name : $account->name.' ·· '.$account->last_four_digits,
            ]);

        return array_values($accounts->all());
    }

    /**
     * The accounts of the family a transfer can go to or come from, by name: the ones that are open, with whether the
     * bank gives their transactions (then the other half of a transfer may be on the list too).
     *
     * @return list<array{id: int, name: string, connected: bool}>
     */
    public function transferAccounts(): array
    {
        $accounts = FinancialAccount::query()
            ->where('status', FinancialAccountStatus::Active)
            ->orderBy('name')
            ->get()
            ->map(static fn (FinancialAccount $account): array => [
                'id' => $account->id,
                'name' => $account->last_four_digits === null ? $account->name : $account->name.' ·· '.$account->last_four_digits,
                'connected' => $account->bank_connection_id !== null,
            ]);

        return array_values($accounts->all());
    }

    /**
     * The transactions of the family the filters let through.
     *
     * @param  BankListRequest  $filters  the filters chosen
     * @return Builder<BankTransaction>
     */
    private function filtered(BankListRequest $filters): Builder
    {
        /** @var Builder<BankTransaction> $query the `@method` lines of the model widen `query()` to a model */
        $query = BankTransaction::query();

        foreach ($filters->words() as $word) {
            $like = '%'.addcslashes($word, '\\%_').'%';
            $query->where(static function (Builder $inner) use ($like): void {
                $inner->where('text', 'like', $like)->orWhere('counterparty', 'like', $like);
            });
        }

        if ($filters->accountId() !== null) {
            $query->where('financial_account_id', $filters->accountId());
        }

        if ($filters->direction() !== null) {
            $query->where('amount', $filters->direction() === 'expense' ? '<' : '>=', 0);
        }

        if ($filters->month() !== null) {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $filters->month().'-01')?->startOfMonth();
            $query->whereRaw('coalesce(booked_on, value_on) between ? and ?', [$start?->toDateString(), $start?->endOfMonth()->toDateString()]);
        }

        if ($filters->status() instanceof BankRowStatus) {
            $this->withStatus($query, $filters->status());
        }

        return $query;
    }

    /**
     * Keeps the transactions of a status: pending first, then transfer, put aside, linked, else to sort (as the row says it).
     *
     * @param  Builder<BankTransaction>  $query  the query
     * @param  BankRowStatus  $status  the status
     */
    private function withStatus(Builder $query, BankRowStatus $status): void
    {
        if ($status === BankRowStatus::Pending) {
            $query->where('status', BankTransactionStatus::Pending);

            return;
        }

        $query->where('status', '!=', BankTransactionStatus::Pending);

        match ($status) {
            BankRowStatus::Ignored => $query->whereNotNull('ignored_at')->whereNull('transfer_account_id'),
            BankRowStatus::Transfer => $query->whereNotNull('transfer_account_id'),
            // A commission joined to the expense of its debit is a fee, not the transaction the expense came from
            BankRowStatus::Linked => $query->whereNull('ignored_at')->whereNull('transfer_account_id')->withoutFees()->where(static fn (Builder $query): Builder => $query->whereNotNull('expense_id')->orWhereNotNull('income_id')),
            BankRowStatus::Fee => $query->whereNull('ignored_at')->whereNull('transfer_account_id')->where('matched_by', BankMatchSource::Fee)->whereNotNull('expense_id'),
            BankRowStatus::Unsorted => $query->whereNull('ignored_at')->whereNull('transfer_account_id')->whereNull('expense_id')->whereNull('income_id'),
        };
    }
}
