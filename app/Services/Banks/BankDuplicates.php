<?php

declare(strict_types=1);

namespace App\Services\Banks;

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
     * The payment of the planned item with the same amount to the cent (a direct debit with its commission counts as
     * the two together, `BankFees::amounts()`), dated within 7 days of the bank transaction, and not already linked to
     * another bank transaction (a commission linked to it does not count). The closest date wins.
     *
     * @param  BankTransaction  $transaction  The bank transaction.
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item it pays.
     */
    public function find(BankTransaction $transaction, PlannedExpense|PlannedIncome $planned): Expense|Income|null
    {
        $day = $transaction->booked_on;

        if (! $day instanceof CarbonImmutable) {
            return null;
        }

        $amounts = $this->fees->amounts($transaction);
        $isExpense = $planned instanceof PlannedExpense;
        $column = $this->column($isExpense);
        $query = $isExpense
            ? Expense::query()->where('planned_expense_id', $planned->id)
            : Income::query()->where('planned_income_id', $planned->id);

        /** @var Expense|Income|null $found */
        $found = $query
            ->whereBetween('date', [$day->subDays(self::DAYS)->toDateString(), $day->addDays(self::DAYS)->toDateString()])
            ->whereIn(DB::raw('round(amount * 100)'), $amounts)
            ->whereNotIn('id', $this->others($transaction)->whereNotNull($column)->select($column))
            ->get()
            ->sortBy(static fn (Expense|Income $payment): int => abs((int) round($day->startOfDay()->diffInDays($payment->date?->startOfDay(), false))))
            ->first();

        return $found;
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
