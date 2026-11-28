<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Actions\Budget\RecordPayment;
use App\Enums\BankMatchSource;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Banks\BankDuplicates;
use App\Services\Banks\BankFees;
use App\Services\Banks\BankMatcher;
use App\Services\Deadlines;
use App\Support\Deadline;
use App\Support\PlannedKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        private BankFees $fees,
        private AttachBankFee $attachFee,
    ) {}

    /**
     * Settles the transaction with the planned item, in one transaction. The bank transaction is locked first and the
     * planned item right after, before anything is read: in this order everywhere, so two settlings, or a settling and
     * *I paid it*, wait for each other instead of locking each other, and every read below sees what they wrote. For
     * the same reason it is not called inside a transaction that has already read something.
     *
     * 1. A payment of the planned item already recorded with the same amount within a few days: the transaction is
     *    linked to it (`duplicate`), without a notice. Not when the person chose the due date (`$occurrence`).
     * 2. Otherwise the due date it settles; with none the transaction stays to sort. When that due date is already paid
     *    (the closest within reach, paid by hand with another amount), the transaction is linked to its payment
     *    (`duplicate`, no notice), or stays to sort when another bank transaction has it: it never pays another month.
     * 3. Otherwise the payment of that due date, for the amount of the bank, on the day it was booked and on its
     *    account, with the fee of the planned expense when it is smaller than the amount (not when the bank writes the
     *    commission apart: then the commission joins the payment by itself); the transaction is linked to it (`$by`)
     *    and everyone in the family gets a notice.
     * 4. A due date found paid while writing is linked to its payment in the same way; one skipped meanwhile leaves the
     *    transaction to sort. No exception leaves.
     *
     * A transaction declined by *It was not this one* is settled only for `Assigned`: the person chose it. Whatever
     * the expense it is linked to, the commission of a direct debit joins it (`AttachBankFee`).
     *
     * @param  BankTransaction  $transaction  The transaction of the bank.
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item it pays.
     * @param  BankMatchSource  $by  How it was recognised: by a rule, or by the person.
     * @param  CarbonImmutable|null  $occurrence  The due date the person chose (the date the rules give), instead of the one the matcher finds.
     * @param  BankFilter|null  $filter  The filter that pays it, which says how the commission of a direct debit is found.
     * @return Expense|Income|null the payment it is now linked to, or null when it stays to sort (or is not to sort any more)
     */
    public function handle(BankTransaction $transaction, PlannedExpense|PlannedIncome $planned, BankMatchSource $by, ?CarbonImmutable $occurrence = null, ?BankFilter $filter = null): Expense|Income|null
    {
        return DB::transaction(function () use ($transaction, $planned, $by, $occurrence, $filter): Expense|Income|null {
            // A person who assigns it settles it even after *It was not this one*; a rule never takes it again
            $current = BankTransaction::query()->unsorted()
                ->when($by !== BankMatchSource::Assigned, static fn (Builder $query): Builder => $query->whereNull('declined_at'))
                ->whereKey($transaction->id)->lockForUpdate()->first();

            if (! $current instanceof BankTransaction || ! $current->booked_on instanceof CarbonImmutable) {
                return null;
            }

            PlannedKind::lock($planned);

            // The person chose the due date: a payment of another one within a few days is not this transaction's
            $existing = $occurrence instanceof CarbonImmutable ? null : $this->duplicates->findAny($current, $planned, $filter);

            // Locked and read again before it is taken: another bank row may have taken it by another path meanwhile
            if ($existing !== null && $this->duplicates->claim($existing, $current)) {
                return $this->link($current, $existing, BankMatchSource::Duplicate, $filter);
            }

            try {
                $deadline = $occurrence instanceof CarbonImmutable ? $this->deadlines->find($planned, $occurrence) : $this->matcher->deadline($current, $planned);
            } catch (NotFoundHttpException) {
                // The date the person chose is not (or not any more) a due date of the planned item
                return null;
            }

            if (! $deadline instanceof Deadline) {
                return null;
            }

            if ($deadline->isPaid()) {
                return $this->linkPaid($current, $deadline, $filter);
            }

            try {
                try {
                    $payment = $this->pay($current, $planned, $deadline, $filter);
                } catch (ValidationException) {
                    // Paid by somebody who did not wait for the lock: only the payment rolled back, the lock is still held
                    return $this->linkPaid($current, $this->deadlines->find($planned, $deadline->occurrence->originalDate), $filter);
                }
            } catch (NotFoundHttpException) {
                // The due date went away meanwhile (the planned item was changed): nothing to pay or link, the transaction stays to sort
                return null;
            }

            $this->link($current, $payment, $by, $filter);
            // Who assigned it by hand knows already; a rule tells everyone
            $assigner = $by === BankMatchSource::Assigned ? Auth::guard('web')->id() : null;
            $this->notify->handle($current, $payment, $deadline, is_numeric($assigner) ? (int) $assigner : null);

            return $payment;
        });
    }

    /**
     * Records the payment of the due date as the bank tells it.
     *
     * @param  BankTransaction  $transaction  The transaction, locked.
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     * @param  Deadline  $deadline  The due date it settles.
     * @param  BankFilter|null  $filter  The filter that pays it.
     *
     * @throws ValidationException if the due date is already paid
     */
    private function pay(BankTransaction $transaction, PlannedExpense|PlannedIncome $planned, Deadline $deadline, ?BankFilter $filter): Expense|Income
    {
        $amount = abs((float) $transaction->amount);
        // A commission the bank writes apart joins the payment when it is there (`AttachBankFee`), never twice
        $fee = $planned instanceof PlannedExpense && ! $this->fees->isDetached($transaction, $filter) ? (float) $planned->fee_amount : 0.0;

        // Otherwise the bank says the whole amount, commission included: a commission as big as the amount cannot be part of it
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
     * Links the transaction to the payment of a paid due date, when no other bank transaction has it (the payment is
     * locked and read again, `BankDuplicates::claim()`).
     *
     * @param  BankTransaction  $transaction  The transaction, locked.
     * @param  Deadline  $deadline  The due date, paid.
     * @param  BankFilter|null  $filter  The filter that pays it.
     * @return Expense|Income|null the payment, or null when the transaction stays to sort
     */
    private function linkPaid(BankTransaction $transaction, Deadline $deadline, ?BankFilter $filter): Expense|Income|null
    {
        $payment = $deadline->payment;

        return $payment !== null && $this->duplicates->claim($payment, $transaction)
            ? $this->link($transaction, $payment, BankMatchSource::Duplicate, $filter)
            : null;
    }

    /**
     * Links the transaction to a payment, and the commission of a direct debit to the same expense.
     *
     * @param  BankTransaction  $transaction  The transaction, locked.
     * @param  Expense|Income  $payment  The payment.
     * @param  BankMatchSource  $by  How the link came.
     * @param  BankFilter|null  $filter  The filter that pays it, which says how the commission is found.
     * @return Expense|Income the payment, read again when a commission joined it
     */
    private function link(BankTransaction $transaction, Expense|Income $payment, BankMatchSource $by, ?BankFilter $filter): Expense|Income
    {
        $transaction->linkTo($payment, $by);
        $this->attachFee->forDebit($transaction, $filter);

        return $payment instanceof Expense ? $payment->refresh() : $payment;
    }
}
