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
 * sort too) and the expense gets back what it had. A commission that was added to the amount comes off the amount and
 * the fee, never below zero; one that was already in the amount (`fee_before` is set, see `AttachBankFee`) leaves the
 * amount alone and puts the fee back to what it was.
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
            $amount = Money::cents($locked->amount);
            $feeAmount = Money::cents($locked->fee_amount);

            foreach ($fees as $fee) {
                if ($fee->fee_before !== null) {
                    // Already in the amount when it joined: the amount stays, the fee goes back to what it was
                    $feeAmount = Money::cents($fee->fee_before);
                } else {
                    $take = abs(Money::cents($fee->amount));
                    $cents += $take;
                    $feeAmount = max(0, $feeAmount - $take);
                }

                $fee->forceFill(['expense_id' => null, 'income_id' => null, 'matched_by' => null, 'fee_before' => null])->save();
            }

            $locked->forceFill([
                'amount' => Money::decimal(max(0, $amount - $cents)),
                'fee_amount' => $feeAmount === 0 ? null : Money::decimal($feeAmount),
            ])->save();
        });
    }
}
