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
    public function __construct(private DeleteMovement $delete) {}

    /**
     * Undoes the payment in one transaction. The bank transaction is locked first and the planned items right after,
     * in order of id, as `SettleFromBank` does, so an undo and a settling wait for each other. Which planned items to
     * lock is read before the transaction starts, so no plain read comes before the locks (MySQL fixes the snapshot at
     * the first one); the payments are read after them and must still settle those planned items. A debit that paid
     * more planned expenses together (`splitExpenses`) takes back all its payments.
     *
     * @param  BankTransaction  $transaction  The transaction linked to the payment.
     *
     * @throws HttpException (422) if the transaction is not linked, was linked as a duplicate or created by hand, or its payments moved to other planned items meanwhile
     */
    public function handle(BankTransaction $transaction): void
    {
        $planned = $this->plannedOf($transaction);

        DB::transaction(function () use ($transaction, $planned): void {
            $current = BankTransaction::query()->whereKey($transaction->id)->lockForUpdate()->first();
            $locked = [];

            foreach ($planned as [$class, $id]) {
                $row = $class::query()->lockForUpdate()->find($id);

                if ($row !== null) {
                    $locked[] = $row->id;
                }
            }

            $payment = $current?->expense_id !== null ? $current->expense : $current?->income;
            $payments = $current?->splitExpenses()->orderBy('planned_expense_id')->get()->all() ?? [];
            $payments = $payments === [] && $payment !== null ? [$payment] : $payments;
            $settled = array_map($this->plannedId(...), $payments);

            if (! $current instanceof BankTransaction || $payment === null || ! in_array($current->matched_by, [BankMatchSource::Rule, BankMatchSource::Assigned], true)
                || $settled !== $locked) {
                abort(422, __('This payment was not recorded by the bank, so it cannot be undone from here.'));
            }

            $current->forceFill(['expense_id' => null, 'income_id' => null, 'matched_by' => null, 'declined_at' => now()])->save();

            foreach ($payments as $item) {
                $this->delete->handle($item);
            }
        });
    }

    /**
     * The class and id of each planned item the payments of the transaction settle, read as they are now, in order of
     * id: one, or more for a debit that paid more planned expenses together; empty when the transaction pays nothing
     * or a payment of no planned item.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @return list<array{class-string<PlannedExpense>|class-string<PlannedIncome>, int}>
     */
    private function plannedOf(BankTransaction $transaction): array
    {
        $current = BankTransaction::query()->with(['expense', 'income', 'splitExpenses'])->find($transaction->id);
        $payment = $current === null ? null : ($current->expense ?? $current->income);

        if ($current !== null && $current->splitExpenses->isNotEmpty()) {
            /** @var list<int> $ids */
            $ids = $current->splitExpenses->pluck('planned_expense_id')->filter()->sort()->values()->all();

            return array_map(static fn (int $id): array => [PlannedExpense::class, $id], $ids);
        }

        $id = $payment === null ? null : $this->plannedId($payment);

        if ($id === null) {
            return [];
        }

        return [[$payment instanceof Expense ? PlannedExpense::class : PlannedIncome::class, $id]];
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
