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
    public function __construct(private DeleteMovement $delete, private DetachBankFee $detachFee) {}

    /**
     * Undoes the payment in one transaction. The bank transaction is locked first and the planned item right after,
     * in the same order as `SettleFromBank`, so an undo and a settling wait for each other. Which planned item to lock
     * is read before the transaction starts, so no plain read comes before the locks (MySQL fixes the snapshot at the
     * first one); the payment is read after them and must still settle that planned item. The commission joined to the
     * expense goes back to sort first (`DetachBankFee`), under its debit; its row is locked with the debit, before
     * the planned item.
     *
     * @param  BankTransaction  $transaction  The transaction linked to the payment.
     *
     * @throws HttpException (422) if the transaction is not linked, was linked as a duplicate or created by hand, or its payment moved to another planned item meanwhile
     */
    public function handle(BankTransaction $transaction): void
    {
        $planned = $this->plannedOf($transaction);

        DB::transaction(function () use ($transaction, $planned): void {
            $current = BankTransaction::query()->whereKey($transaction->id)->lockForUpdate()->first();

            if ($current?->expense_id !== null) {
                BankTransaction::query()->where('expense_id', $current->expense_id)->where('matched_by', BankMatchSource::Fee)->lockForUpdate()->get();
            }

            $locked = $planned === null ? null : $planned[0]::query()->lockForUpdate()->find($planned[1]);
            $payment = $current?->expense_id !== null ? $current->expense : $current?->income;

            if (! $current instanceof BankTransaction || $payment === null || ! in_array($current->matched_by, [BankMatchSource::Rule, BankMatchSource::Assigned], true)
                || $this->plannedId($payment) !== $locked?->id) {
                abort(422, __('This payment was not recorded by the bank, so it cannot be undone from here.'));
            }

            if ($payment instanceof Expense) {
                $this->detachFee->handle($payment);
            }

            $current->forceFill(['expense_id' => null, 'income_id' => null, 'matched_by' => null, 'declined_at' => now()])->save();
            $this->delete->handle($payment);
        });
    }

    /**
     * The class and id of the planned item the payment of the transaction settles, read as it is now; null when the
     * transaction pays nothing or a payment of no planned item.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @return array{class-string<PlannedExpense>|class-string<PlannedIncome>, int}|null
     */
    private function plannedOf(BankTransaction $transaction): ?array
    {
        $current = BankTransaction::query()->with(['expense', 'income'])->find($transaction->id);
        $payment = $current === null ? null : ($current->expense ?? $current->income);
        $id = $payment === null ? null : $this->plannedId($payment);

        if ($id === null) {
            return null;
        }

        return [$payment instanceof Expense ? PlannedExpense::class : PlannedIncome::class, $id];
    }

    /**
     * The id of the planned item a payment settles, if any.
     *
     * @param  Expense|Income  $payment  The payment.
     */
    private function plannedId(Expense|Income $payment): ?int
    {
        return $payment instanceof Expense ? $payment->planned_expense_id : $payment->planned_income_id;
    }
}
