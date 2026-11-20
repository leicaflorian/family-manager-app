<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Actions\Budget\DeleteMovement;
use App\Enums\BankMatchSource;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\PlannedKind;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * *It was not this one*: takes back a payment the bank recorded. The expense or income goes (its documents stay), the
 * due date it settled is to pay again, and the transaction goes back to *From the bank*, marked as declined so that no
 * rule takes it again. The notices of the bell about it stay: they fall back to *From the bank* once the payment is
 * gone.
 */
final readonly class UndoBankPayment
{
    public function __construct(private DeleteMovement $delete) {}

    /**
     * Undoes the payment in one transaction. The bank transaction is locked first and the planned item right after,
     * in the same order as `SettleFromBank`, so an undo and a settling wait for each other.
     *
     * @param  BankTransaction  $transaction  The transaction linked to the payment.
     *
     * @throws HttpException (422) if the transaction is not linked, or was linked as a duplicate or created by hand
     */
    public function handle(BankTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $current = BankTransaction::query()->whereKey($transaction->id)->lockForUpdate()->first();
            $payment = $current?->expense_id !== null ? $current->expense : $current?->income;

            if (! $current instanceof BankTransaction || $payment === null || ! in_array($current->matched_by, [BankMatchSource::Rule, BankMatchSource::Assigned], true)) {
                abort(422, __('This payment was not recorded by the bank, so it cannot be undone from here.'));
            }

            $planned = $this->planned($payment);

            if ($planned !== null) {
                PlannedKind::lock($planned);
            }

            $current->forceFill(['expense_id' => null, 'income_id' => null, 'matched_by' => null, 'declined_at' => now()])->save();
            $this->delete->handle($payment);
        });
    }

    /**
     * The planned item the payment settles, if any.
     *
     * @param  Expense|Income  $payment  The payment.
     */
    private function planned(Expense|Income $payment): PlannedExpense|PlannedIncome|null
    {
        return $payment instanceof Expense
            ? PlannedExpense::query()->find($payment->planned_expense_id)
            : PlannedIncome::query()->find($payment->planned_income_id);
    }
}
