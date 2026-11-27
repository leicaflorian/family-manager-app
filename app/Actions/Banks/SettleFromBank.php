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
use App\Services\Banks\BankSplit;
use App\Services\Deadlines;
use App\Support\Banks\BankSplitMatch;
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
        private BankSplit $split,
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
     * 4. A due date found paid while writing is linked to its payment in the same way; one skipped meanwhile leaves the
     *    transaction to sort. No exception leaves.
     *
     * A transaction declined by *It was not this one* is settled only for `Assigned`: the person chose it.
     *
     * @param  BankTransaction  $transaction  The transaction of the bank.
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item it pays.
     * @param  BankMatchSource  $by  How it was recognised: by a rule, or by the person.
     * @return Expense|Income|null the payment it is now linked to, or null when it stays to sort (or is not to sort any more)
     */
    public function handle(BankTransaction $transaction, PlannedExpense|PlannedIncome $planned, BankMatchSource $by): Expense|Income|null
    {
        return DB::transaction(function () use ($transaction, $planned, $by): Expense|Income|null {
            // A person who assigns it settles it even after *It was not this one*; a rule never takes it again
            $current = BankTransaction::query()->unsorted()
                ->when($by !== BankMatchSource::Assigned, static fn (Builder $query): Builder => $query->whereNull('declined_at'))
                ->whereKey($transaction->id)->lockForUpdate()->first();

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
                try {
                    $payment = $this->record($current, $deadline, abs((float) $current->amount));
                } catch (ValidationException) {
                    // Paid by somebody who did not wait for the lock: only the payment rolled back, the lock is still held
                    return $this->linkPaid($current, $this->deadlines->find($planned, $deadline->occurrence->originalDate));
                }
            } catch (NotFoundHttpException) {
                // The due date went away meanwhile (the planned item was changed): nothing to pay or link, the transaction stays to sort
                return null;
            }

            $this->link($current, $payment, $by);
            // Who assigned it by hand knows already; a rule tells everyone
            $this->notify->handle($current, $payment, $deadline, $this->assigner($by));

            return $payment;
        });
    }

    /**
     * Settles a debit that pays more planned expenses together (`BankSplit`), in one transaction. The bank transaction
     * is locked first, then the planned expenses in order of id, before anything is read, as in `handle`. Under the
     * locks the split is found again: when it is no longer the same (a due date paid or changed meanwhile) nothing is
     * written and the debit goes to the rule of one planned expense.
     *
     * - `pay`: one payment for each due date, for the amount of the due date, on the day the debit was booked and on
     *   its account, and one notice for everyone in the family about the debit;
     * - `link`: the payments already recorded, without a notice.
     *
     * Every payment gets the debit in `bank_transaction_id`, and the debit is linked to the first one (`$by` for `pay`,
     * `Duplicate` for `link`). A transaction declined by *It was not this one* is settled only for `Assigned`.
     *
     * @param  BankTransaction  $transaction  The transaction of the bank.
     * @param  BankSplitMatch  $match  The split found before the locks.
     * @param  BankMatchSource  $by  How it was recognised: by a rule, or by the person.
     * @return list<Expense> the payments, in order of id of their planned expense; empty when nothing was settled
     */
    public function split(BankTransaction $transaction, BankSplitMatch $match, BankMatchSource $by): array
    {
        return DB::transaction(function () use ($transaction, $match, $by): array {
            $current = BankTransaction::query()->unsorted()
                ->when($by !== BankMatchSource::Assigned, static fn (Builder $query): Builder => $query->whereNull('declined_at'))
                ->whereKey($transaction->id)->lockForUpdate()->first();

            if (! $current instanceof BankTransaction || ! $current->booked_on instanceof CarbonImmutable) {
                return [];
            }

            $planned = array_map(static fn (Deadline $deadline): PlannedExpense|PlannedIncome => $deadline->planned, $match->deadlines);
            usort($planned, static fn (PlannedExpense|PlannedIncome $a, PlannedExpense|PlannedIncome $b): int => $a->id <=> $b->id);

            foreach ($planned as $item) {
                PlannedKind::lock($item);
            }

            $again = $this->split->find($current);

            if (! $again instanceof BankSplitMatch || ! $match->sameAs($again)) {
                return [];
            }

            $payments = $again->mode === 'pay' ? $this->payAll($current, $again) : $again->payments;

            if ($payments === []) {
                return [];
            }

            foreach ($payments as $payment) {
                $payment->forceFill(['bank_transaction_id' => $current->id])->save();
            }

            $this->link($current, $payments[0], $again->mode === 'pay' ? $by : BankMatchSource::Duplicate);

            if ($again->mode === 'pay') {
                $this->notify->handleSplit($current, $payments, $this->assigner($by));
            }

            return $payments;
        });
    }

    /**
     * Records one payment for each due date of a split, for the amount of the due date, all or none: a due date paid
     * or gone meanwhile rolls back the ones already written and gives none.
     *
     * @param  BankTransaction  $transaction  The transaction, locked.
     * @param  BankSplitMatch  $match  The split, found under the locks.
     * @return list<Expense>
     */
    private function payAll(BankTransaction $transaction, BankSplitMatch $match): array
    {
        try {
            return DB::transaction(function () use ($transaction, $match): array {
                $payments = [];

                foreach ($match->deadlines as $deadline) {
                    $payment = $this->record($transaction, $deadline, $deadline->occurrence->amount);

                    if ($payment instanceof Expense) {
                        $payments[] = $payment;
                    }
                }

                return $payments;
            });
        } catch (ValidationException|NotFoundHttpException) {
            return [];
        }
    }

    /**
     * The account that assigned the transaction by hand, who knows already and gets no notice; none for a rule.
     *
     * @param  BankMatchSource  $by  How it was recognised.
     */
    private function assigner(BankMatchSource $by): ?int
    {
        $assigner = $by === BankMatchSource::Assigned ? Auth::guard('web')->id() : null;

        return is_numeric($assigner) ? (int) $assigner : null;
    }

    /**
     * Records the payment of a due date for an amount, on the day the transaction was booked and on its account, with
     * the fee of the planned expense when it is smaller than the amount.
     *
     * @param  BankTransaction  $transaction  The transaction, locked.
     * @param  Deadline  $deadline  The due date it settles.
     * @param  float  $amount  The amount of the payment.
     *
     * @throws ValidationException if the due date is already paid
     */
    private function record(BankTransaction $transaction, Deadline $deadline, float $amount): Expense|Income
    {
        $planned = $deadline->planned;
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
        $transaction->linkTo($payment, $by);

        return $payment;
    }
}
