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
 * *It was not this one*: takes back what the bank did. A payment it recorded (by a rule, a filter or the person's
 * choice) goes: the expense or income is deleted (its documents stay), the due date it settled is to pay again. A link
 * to an expense or income that was already there is only taken off: the expense stays as it was, and the pair is added
 * to `rejected_proposals` as `expense:<id>` or `income:<id>` so it is not proposed again. Either way the transaction
 * goes back to *From the bank*, marked as declined so that no rule or filter takes it again. The notices of the bell
 * about it stay: they fall back to *From the bank* once the payment is gone.
 */
final readonly class UndoBankPayment
{
    /** How a link came for the transactions that can be taken back from here. */
    private const array UNDOABLE = [BankMatchSource::Rule, BankMatchSource::Proposed, BankMatchSource::Assigned, BankMatchSource::Duplicate];

    public function __construct(private DeleteMovement $delete, private DetachBankFee $detachFee) {}

    /**
     * Undoes the payment in one transaction. The bank transaction is locked first and the planned item right after,
     * in the same order as `SettleFromBank`, so an undo and a settling wait for each other. Which planned item to lock
     * is read before the transaction starts, so no plain read comes before the locks (MySQL fixes the snapshot at the
     * first one); the payment is read after them and must still settle that planned item. The commission joined to the
     * expense goes back to sort first (`DetachBankFee`), under its debit; its row is locked right after the planned
     * item, in the order of a settling.
     *
     * @param  BankTransaction  $transaction  The transaction linked to the payment.
     * @param  bool  $detachOnly  What the caller was allowed to do: true when it was authorised only to unlink a movement that was already there, false when it was authorised to delete a payment the bank recorded; the row under the lock must still be of that kind
     *
     * @throws HttpException (422) if the transaction is not linked, was created by hand or is a commission, changed kind (payment or link to an expense that was already there) since the person was allowed, or its payment moved to another planned item meanwhile
     */
    public function handle(BankTransaction $transaction, bool $detachOnly = false): void
    {
        $planned = $this->plannedOf($transaction);

        DB::transaction(function () use ($transaction, $planned, $detachOnly): void {
            $current = BankTransaction::query()->whereKey($transaction->id)->lockForUpdate()->first();
            $locked = $planned === null ? null : $planned[0]::query()->lockForUpdate()->find($planned[1]);

            // The commission rows after the planned item, as a settling locks them (`AttachBankFee` after the link)
            if ($current?->expense_id !== null) {
                BankTransaction::query()->where('expense_id', $current->expense_id)->where('matched_by', BankMatchSource::Fee)->lockForUpdate()->get();
            }
            $payment = $current?->expense_id !== null ? $current->expense : $current?->income;

            if (! $current instanceof BankTransaction || $payment === null || ! in_array($current->matched_by, self::UNDOABLE, true)
                || ($current->matched_by === BankMatchSource::Duplicate) !== $detachOnly
                || $this->plannedId($payment) !== $locked?->id) {
                abort(422, __('This payment was not recorded by the bank, so it cannot be undone from here.'));
            }

            if ($payment instanceof Expense) {
                $this->detachFee->handle($payment);
            }

            $unlinked = ['expense_id' => null, 'income_id' => null, 'matched_by' => null, 'bank_filter_id' => null, 'declined_at' => now()];

            if ($current->matched_by === BankMatchSource::Duplicate) {
                // The movement was already there: it stays, and the pair is not proposed again
                $current->forceFill([...$unlinked, 'rejected_proposals' => $this->withPair($current, $payment)])->save();

                return;
            }

            $current->forceFill($unlinked)->save();
            $this->delete->handle($payment);
        });
    }

    /**
     * The rejected proposals of the transaction with the pair of the transaction and the movement added, as
     * `expense:<id>` or `income:<id>` (a list of strings, beside the `group:<key>` ones of the groups).
     *
     * @param  BankTransaction  $transaction  the transaction
     * @param  Expense|Income  $movement  the movement it was linked to
     * @return array<array-key, mixed>
     */
    private function withPair(BankTransaction $transaction, Expense|Income $movement): array
    {
        $pair = ($movement instanceof Expense ? 'expense:' : 'income:').$movement->id;

        $rejected = $transaction->rejected_proposals ?? [];

        // Spread keeps the string keys (`group:<key>` => true) and numbers the rest
        return in_array($pair, $rejected, true) ? $rejected : [...$rejected, $pair];
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
