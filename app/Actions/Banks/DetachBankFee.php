<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Enums\BankMatchSource;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Takes the commission back out of an expense: the commission goes back to sort (under its debit, when the debit is to
 * sort too) and its amount comes off the amount and the fee of the expense, never below zero.
 */
final readonly class DetachBankFee
{
    /**
     * Detaches the commission of the expense, in one transaction: the commission rows are locked first, then the
     * expense, as `AttachBankFee` does. An expense without a commission is left as it is.
     *
     * @param  Expense  $expense  the expense
     */
    public function handle(Expense $expense): void
    {
        DB::transaction(function () use ($expense): void {
            $fees = BankTransaction::query()->where('expense_id', $expense->id)->where('matched_by', BankMatchSource::Fee)->lockForUpdate()->get();
            $locked = $fees->isEmpty() ? null : Expense::query()->lockForUpdate()->find($expense->id);

            if (! $locked instanceof Expense) {
                return;
            }

            $cents = 0;

            foreach ($fees as $fee) {
                $cents += abs(Money::cents($fee->amount));
                $fee->forceFill(['expense_id' => null, 'income_id' => null, 'matched_by' => null])->save();
            }

            $feeAmount = max(0, Money::cents($locked->fee_amount) - $cents);

            $locked->forceFill([
                'amount' => Money::decimal(max(0, Money::cents($locked->amount) - $cents)),
                'fee_amount' => $feeAmount === 0 ? null : Money::decimal($feeAmount),
            ])->save();
        });
    }
}
