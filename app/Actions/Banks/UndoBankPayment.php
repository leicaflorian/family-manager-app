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
 * goes back to *From the bank*, marked as declined so that no rule or filter takes it again. A debit that paid more
 * planned expenses together (a bill of electricity and gas, `BankTransaction::splitPayments()`) takes back all of them
 * the same way: all its payments go, or all its links come off. The notices of the bell about it stay: they fall back
 * to *From the bank* once the payment is gone.
 */
final readonly class UndoBankPayment
{
    /** How a link came for the transactions that can be taken back from here. */
    private const array UNDOABLE = [BankMatchSource::Rule, BankMatchSource::Proposed, BankMatchSource::Assigned, BankMatchSource::Duplicate];

    public function __construct(private DeleteMovement $delete, private DetachBankFee $detachFee) {}

    /**
     * Undoes the payment in one transaction. The bank transaction is locked first and the planned items right after,
     * in order of id, as `SettleFromBank` does, so an undo and a settling wait for each other. Which planned items to
     * lock is read before the transaction starts, so no plain read comes before the locks (MySQL fixes the snapshot at
     * the first one); the payments are read after them and must still settle those planned items. The commission
     * joined to the expense (the first one of a split) goes back to sort first (`DetachBankFee`), under its debit; its
     * row is locked right after the planned items, in the order of a settling.
     *
     * @param  BankTransaction  $transaction  The transaction linked to the payment.
     * @param  bool  $detachOnly  What the caller was allowed to do: true when it was authorised only to unlink a movement that was already there, false when it was authorised to delete a payment the bank recorded; the row under the lock must still be of that kind
     *
     * @throws HttpException (422) if the transaction is not linked, was created by hand or is a commission, changed kind (payment or link to an expense that was already there) since the person was allowed, or its payments moved to other planned items meanwhile
     */
    public function handle(BankTransaction $transaction, bool $detachOnly = false): void
    {
        $planned = $this->plannedOf($transaction);

        DB::transaction(function () use ($transaction, $planned, $detachOnly): void {
            $current = BankTransaction::query()->whereKey($transaction->id)->lockForUpdate()->first();
            $locked = [];

            foreach ($planned as [$class, $id]) {
                $row = $class::query()->lockForUpdate()->find($id);

                if ($row !== null) {
                    $locked[] = $row->id;
                }
            }

            // The commission rows after the planned item, as a settling locks them (`AttachBankFee` after the link)
            if ($current?->expense_id !== null) {
                BankTransaction::query()->where('expense_id', $current->expense_id)->where('matched_by', BankMatchSource::Fee)->lockForUpdate()->get();
            }
            $payment = $current?->expense_id !== null ? $current->expense : $current?->income;
            $payments = $current?->splitPayments() ?? [];
            $payments = $payments === [] && $payment !== null ? [$payment] : $payments;

            if (! $current instanceof BankTransaction || $payment === null || ! in_array($current->matched_by, self::UNDOABLE, true)
                || ($current->matched_by === BankMatchSource::Duplicate) !== $detachOnly
                || $this->plannedIds($payments) !== $locked) {
                abort(422, __('This payment was not recorded by the bank, so it cannot be undone from here.'));
            }

            if ($payment instanceof Expense) {
                $this->detachFee->handle($payment);
            }

            $unlinked = ['expense_id' => null, 'income_id' => null, 'matched_by' => null, 'bank_filter_id' => null, 'declined_at' => now()];
            $current->releaseSplit();

            if ($current->matched_by === BankMatchSource::Duplicate) {
                // The movements were already there: they stay, and the pairs are not proposed again
                $current->forceFill([...$unlinked, 'rejected_proposals' => $this->withPairs($current, $payments)])->save();

                return;
            }

            $current->forceFill($unlinked)->save();

            foreach ($payments as $item) {
                $this->delete->handle($item);
            }
        });
    }

    /**
     * The rejected proposals of the transaction with the pairs of the transaction and the movements added, as
     * `expense:<id>` or `income:<id>` (a list of strings, beside the `group:<key>` ones of the groups).
     *
     * @param  BankTransaction  $transaction  the transaction
     * @param  list<Expense|Income>  $movements  the movements it was linked to
     * @return array<array-key, mixed>
     */
    private function withPairs(BankTransaction $transaction, array $movements): array
    {
        $rejected = $transaction->rejected_proposals ?? [];

        foreach ($movements as $movement) {
            $pair = ($movement instanceof Expense ? 'expense:' : 'income:').$movement->id;

            // Spread keeps the string keys (`group:<key>` => true) and numbers the rest
            $rejected = in_array($pair, $rejected, true) ? $rejected : [...$rejected, $pair];
        }

        return $rejected;
    }

    /**
     * The class and id of each planned item the payments of the transaction settle, read as they are now, in order of
     * id: one, or more for a debit that paid more planned expenses together; empty when the transaction pays nothing
     * or payments of no planned item.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @return list<array{class-string<PlannedExpense>|class-string<PlannedIncome>, int}>
     */
    private function plannedOf(BankTransaction $transaction): array
    {
        $current = BankTransaction::query()->with(['expense', 'income', 'splitExpenses'])->find($transaction->id);
        $payment = $current === null ? null : ($current->expense ?? $current->income);
        $payments = $current?->splitPayments() ?? [];
        $payments = $payments === [] && $payment !== null ? [$payment] : $payments;
        $class = $payment instanceof Income ? PlannedIncome::class : PlannedExpense::class;

        return array_map(static fn (int $id): array => [$class, $id], $this->plannedIds($payments));
    }

    /**
     * The ids of the planned items the payments settle, in order, leaving out the payments of no planned item.
     *
     * @param  list<Expense|Income>  $payments  The payments.
     * @return list<int>
     */
    private function plannedIds(array $payments): array
    {
        $ids = array_values(array_filter(array_map($this->plannedId(...), $payments), static fn (?int $id): bool => $id !== null));
        sort($ids);

        return $ids;
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
