<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Actions\Budget\RecordPayment;
use App\Enums\BankMatchSource;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Banks\BankDuplicates;
use App\Services\Banks\BankMatcher;
use App\Services\Deadlines;
use App\Support\Deadline;
use App\Support\PlannedKind;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Settles a due date with a bank transaction: links it to the payment somebody already recorded, or records the
 * payment of the due date it settles as *I paid it* does, and tells the family. Runs in a request and in the queued
 * read of a bank, so it never needs a signed-in account.
 */
final readonly class SettleFromBank
{
    public function __construct(
        private BankDuplicates $duplicates,
        private BankMatcher $matcher,
        private Deadlines $deadlines,
        private RecordPayment $recordPayment,
        private NotifyBankPayment $notify,
    ) {}

    /**
     * Settles the transaction with the planned item, in one transaction. The bank transaction is locked first and the
     * planned item right after, before anything is read: in this order everywhere, so two settlings, or a settling and
     * *I paid it*, wait for each other instead of locking each other, and every read below sees what they wrote. For
     * the same reason it is not called inside a transaction that has already read something.
     *
     * 1. A payment of the planned item already recorded with the same amount within a few days: the transaction is
     *    linked to it (`duplicate`), without a notice.
     * 2. Otherwise the due date it settles; with none the transaction stays to sort. When that due date is already paid
     *    (the closest within reach, paid by hand with another amount), the transaction is linked to its payment
     *    (`duplicate`, no notice), or stays to sort when another bank transaction has it: it never pays another month.
     * 3. Otherwise the payment of that due date, for the amount of the bank, on the day it was booked and on its
     *    account, with the fee of the planned expense when it is smaller than the amount; the transaction is linked
     *    to it (`$by`) and everyone in the family gets a notice.
     * 4. A due date found paid while writing is linked to its payment in the same way. No exception leaves.
     *
     * @param  BankTransaction  $transaction  The transaction of the bank.
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item it pays.
     * @param  BankMatchSource  $by  How it was recognised: by a rule, or by the person.
     * @return Expense|Income|null the payment it is now linked to, or null when it stays to sort (or is not to sort any more)
     */
    public function handle(BankTransaction $transaction, PlannedExpense|PlannedIncome $planned, BankMatchSource $by): Expense|Income|null
    {
        return DB::transaction(function () use ($transaction, $planned, $by): Expense|Income|null {
            $current = BankTransaction::query()->unsorted()->whereNull('declined_at')->whereKey($transaction->id)->lockForUpdate()->first();

            if (! $current instanceof BankTransaction || ! $current->booked_on instanceof CarbonImmutable) {
                return null;
            }

            PlannedKind::lock($planned);

            $existing = $this->duplicates->find($current, $planned);

            if ($existing !== null) {
                return $this->link($current, $existing, BankMatchSource::Duplicate);
            }

            $deadline = $this->matcher->deadline($current, $planned);

            if (! $deadline instanceof Deadline) {
                return null;
            }

            if ($deadline->isPaid()) {
                return $this->linkPaid($current, $deadline);
            }

            try {
                $payment = $this->pay($current, $planned, $deadline);
            } catch (ValidationException) {
                // Paid by somebody who did not wait for the lock: only the payment rolled back, the lock is still held
                return $this->linkPaid($current, $this->deadlines->find($planned, $deadline->occurrence->originalDate));
            }

            $this->link($current, $payment, $by);
            $this->notify->handle($current, $payment, $deadline);

            return $payment;
        });
    }

    /**
     * Records the payment of the due date as the bank tells it.
     *
     * @param  BankTransaction  $transaction  The transaction, locked.
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     * @param  Deadline  $deadline  The due date it settles.
     *
     * @throws ValidationException if the due date is already paid
     */
    private function pay(BankTransaction $transaction, PlannedExpense|PlannedIncome $planned, Deadline $deadline): Expense|Income
    {
        $amount = abs((float) $transaction->amount);
        $fee = $planned instanceof PlannedExpense ? (float) $planned->fee_amount : 0.0;

        // The bank says the whole amount, commission included: a commission as big as the amount cannot be part of it
        return $this->recordPayment->handle(
            $deadline,
            $transaction->booked_on ?? CarbonImmutable::now('Europe/Rome'),
            $amount,
            $transaction->financial_account_id,
            null,
            $fee > 0 && round($fee * 100) < round($amount * 100) ? $fee : null,
        );
    }

    /**
     * Links the transaction to the payment of a paid due date, when no other bank transaction has it.
     *
     * @param  BankTransaction  $transaction  The transaction, locked.
     * @param  Deadline  $deadline  The due date, paid.
     * @return Expense|Income|null the payment, or null when the transaction stays to sort
     */
    private function linkPaid(BankTransaction $transaction, Deadline $deadline): Expense|Income|null
    {
        $payment = $deadline->payment;

        return $payment !== null && $this->duplicates->isFree($payment, $transaction)
            ? $this->link($transaction, $payment, BankMatchSource::Duplicate)
            : null;
    }

    /**
     * Links the transaction to a payment.
     *
     * @param  BankTransaction  $transaction  The transaction, locked.
     * @param  Expense|Income  $payment  The payment.
     * @param  BankMatchSource  $by  How the link came.
     */
    private function link(BankTransaction $transaction, Expense|Income $payment, BankMatchSource $by): Expense|Income
    {
        $transaction->forceFill([
            'expense_id' => $payment instanceof Expense ? $payment->id : null,
            'income_id' => $payment instanceof Income ? $payment->id : null,
            'matched_by' => $by,
        ])->save();

        return $payment;
    }
}
