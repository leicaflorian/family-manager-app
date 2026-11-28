<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Finds the expense or income somebody already recorded by hand for a payment that the bank now reports, so the bank
 * transaction links to it instead of creating a second one, and tells whether a payment is still free to link.
 */
final readonly class BankDuplicates
{
    /** How many days apart the recorded payment and the bank transaction can be. */
    public const int DAYS = 7;

    public function __construct(private BankFees $fees) {}

    /**
     * The expense or income somebody already recorded for the bank transaction: free (not linked to another bank
     * transaction; a commission linked to it does not count), of the same direction, with the same amount to the cent
     * (a direct debit with its commission counts as the two together, `BankFees::amounts()`, the commission found as
     * the filter says), dated within 7 days of the bank transaction, before or after. With a planned item only its
     * payments count (a filter that pays a due date, a settling). The closest date wins.
     *
     * @param  BankTransaction  $transaction  The bank transaction.
     * @param  PlannedExpense|PlannedIncome|null  $planned  The planned item it pays, to look only among its payments.
     * @param  BankFilter|null  $filter  The filter that takes the transaction, which says how its commission is found.
     * @param  list<int>  $skip  ids of payments to treat as taken already (the preview of a filter, which links nothing, skips the ones an earlier row would take)
     */
    public function findAny(BankTransaction $transaction, PlannedExpense|PlannedIncome|null $planned = null, ?BankFilter $filter = null, array $skip = []): Expense|Income|null
    {
        $day = $transaction->booked_on;

        if (! $day instanceof CarbonImmutable) {
            return null;
        }

        $amounts = $this->fees->amounts($transaction, $filter);
        $isExpense = $planned === null ? $transaction->kind() === 'expense' : $planned instanceof PlannedExpense;
        $column = $this->column($isExpense);
        $query = $isExpense ? Expense::query() : Income::query();

        if ($planned !== null) {
            $query->where($isExpense ? 'planned_expense_id' : 'planned_income_id', $planned->id);
        }

        /** @var Expense|Income|null $found */
        $found = $query
            ->whereBetween('date', [$day->subDays(self::DAYS)->toDateString(), $day->addDays(self::DAYS)->toDateString()])
            ->whereIn(DB::raw('round(amount * 100)'), $amounts)
            ->whereNotIn('id', $this->others($transaction)->whereNotNull($column)->select($column))
            ->when($skip !== [], static fn (Builder $query) => $query->whereNotIn('id', $skip))
            ->get()
            ->sortBy(static fn (Expense|Income $payment): int => abs((int) round($day->startOfDay()->diffInDays($payment->date?->startOfDay(), false))))
            ->first();

        return $found;
    }

    /**
     * The ids of the payments of one direction among the keys of what earlier transactions would take
     * (`expense:<id>`, `income:<id>`), as `findAny()` skips them.
     *
     * @param  list<string>  $used  the keys
     * @param  string  $kind  `expense` or `income`
     * @return list<int>
     */
    public static function idsOf(array $used, string $kind): array
    {
        $ids = [];

        foreach ($used as $key) {
            if (str_starts_with($key, $kind.':')) {
                $ids[] = (int) substr($key, strlen($kind) + 1);
            }
        }

        return $ids;
    }

    /**
     * Takes a payment for the bank transaction inside the transaction that links them: the payment is locked, then
     * the other bank transactions that have it are read with a locking read, which sees what another transaction
     * committed while this one waited. Two bank transactions that found the same free payment at the same moment
     * take it once: the second finds it taken. Called after the bank row (and the planned item) are locked.
     *
     * @param  Expense|Income  $payment  The payment found free (`findAny()`).
     * @param  BankTransaction  $transaction  The bank transaction, locked.
     * @return bool whether the payment is still there and free
     */
    public function claim(Expense|Income $payment, BankTransaction $transaction): bool
    {
        if (! $payment->newQuery()->whereKey($payment->getKey())->lockForUpdate()->exists()) {
            return false;
        }

        return ! $this->others($transaction)
            ->where($this->column($payment instanceof Expense), $payment->getKey())
            ->lockForUpdate()
            ->exists();
    }

    /**
     * Whether a payment is free for the bank transaction: not linked to another bank transaction. The commission of a
     * direct debit joined to it does not take it.
     *
     * @param  Expense|Income  $payment  The payment.
     * @param  BankTransaction  $transaction  The bank transaction that would link to it.
     */
    public function isFree(Expense|Income $payment, BankTransaction $transaction): bool
    {
        return ! $this->others($transaction)
            ->where($this->column($payment instanceof Expense), $payment->id)
            ->exists();
    }

    /**
     * The other bank transactions that can take a payment: all but this one and the commissions (`fee`).
     *
     * @param  BankTransaction  $transaction  the bank transaction
     * @return Builder<BankTransaction>
     */
    private function others(BankTransaction $transaction): Builder
    {
        /** @var Builder<BankTransaction> $query */
        $query = BankTransaction::query()->whereKeyNot($transaction->id)->withoutFees();

        return $query;
    }

    /**
     * The column of a bank transaction that links it to an expense or to an income.
     *
     * @param  bool  $expense  Whether the payment is an expense.
     */
    private function column(bool $expense): string
    {
        return $expense ? 'expense_id' : 'income_id';
    }
}
