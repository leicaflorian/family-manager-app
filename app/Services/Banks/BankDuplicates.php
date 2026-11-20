<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use Carbon\CarbonImmutable;

/**
 * Finds the expense or income somebody already recorded by hand for a payment that the bank now reports, so the bank
 * transaction links to it instead of creating a second one.
 */
final class BankDuplicates
{
    /** How many days apart the recorded payment and the bank transaction can be. */
    private const int DAYS = 5;

    /**
     * The payment of the planned item with the same amount to the cent, dated within 5 days of the bank transaction,
     * and not already linked to another bank transaction. The closest date wins.
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

        $cents = (int) round(abs((float) $transaction->amount) * 100);
        $isExpense = $planned instanceof PlannedExpense;
        $column = $isExpense ? 'expense_id' : 'income_id';
        $query = $isExpense
            ? Expense::query()->where('planned_expense_id', $planned->id)
            : Income::query()->where('planned_income_id', $planned->id);

        /** @var Expense|Income|null $found */
        $found = $query
            ->whereBetween('date', [$day->subDays(self::DAYS)->toDateString(), $day->addDays(self::DAYS)->toDateString()])
            ->whereRaw('round(amount * 100) = ?', [$cents])
            ->whereNotIn('id', BankTransaction::query()->whereKeyNot($transaction->id)->whereNotNull($column)->select($column))
            ->get()
            ->sortBy(static fn (Expense|Income $payment): int => abs((int) round($day->startOfDay()->diffInDays($payment->date?->startOfDay(), false))))
            ->first();

        return $found;
    }
}
