<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Enums\BankFilterFee;
use App\Enums\BankMatchSource;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Services\Banks\BankFees;
use App\Support\Banks\BankText;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Joins the commission of a direct debit to the expense of its debit: the commission is linked to it (`fee`) and its
 * amount is added to the amount and to the fee of the expense, since the fee is part of the amount. A commission is
 * added once: what says so is the `fee` transaction already linked to the expense, not the value of `fee_amount`.
 */
final readonly class AttachBankFee
{
    public function __construct(private BankFees $fees) {}

    /**
     * Joins the commission to the expense, in one transaction: the commission is locked first, then the commission
     * rows the expense already has, then the expense (bank rows before the expense, as everywhere), and those rows are
     * read again under the lock of the expense. Nothing happens
     * when the commission is not to sort any more or the expense already has a commission. With the debit given, an
     * expense whose amount is already the debit plus the commission (recorded by hand as the bank charged it) keeps
     * its amount and takes only the fee.
     *
     * @param  BankTransaction  $fee  the commission
     * @param  Expense  $expense  the expense of its debit
     * @param  BankTransaction|null  $debit  the debit, to tell an amount that has the commission in it already
     * @return bool whether the commission joined the expense
     */
    public function handle(BankTransaction $fee, Expense $expense, ?BankTransaction $debit = null): bool
    {
        return DB::transaction(function () use ($fee, $expense, $debit): bool {
            $current = BankTransaction::query()->unsorted()->whereKey($fee->id)->lockForUpdate()->first();

            if (! $current instanceof BankTransaction || $current->kind() !== 'expense') {
                return false;
            }

            $taken = BankTransaction::query()->where('expense_id', $expense->id)->where('matched_by', BankMatchSource::Fee)->lockForUpdate()->get();
            $locked = Expense::query()->lockForUpdate()->find($expense->id);

            if ($taken->isNotEmpty() || ! $locked instanceof Expense) {
                return false;
            }

            // Read again under the lock of the expense: a commission joined meanwhile (sent twice by the bank) waited
            // on this lock and is seen now, so the second one adds nothing
            if (BankTransaction::query()->where('expense_id', $locked->id)->where('matched_by', BankMatchSource::Fee)->lockForUpdate()->exists()) {
                return false;
            }

            $cents = abs(Money::cents($current->amount));
            $amount = Money::cents($locked->amount);
            $feeAmount = Money::cents($locked->fee_amount);
            $included = $debit instanceof BankTransaction && $amount === abs(Money::cents($debit->amount)) + $cents;

            $current->linkTo($locked, BankMatchSource::Fee);
            // What `DetachBankFee` needs to give the expense back what it had: the fee before, only when the amount was not changed
            $current->forceFill(['fee_before' => $included ? Money::decimal($feeAmount) : null])->save();
            $locked->forceFill([
                'amount' => Money::decimal($included ? $amount : $amount + $cents),
                'fee_amount' => Money::decimal($included ? max($feeAmount, $cents) : $feeAmount + $cents),
            ])->save();

            return true;
        });
    }

    /**
     * Joins the commission of a debit just linked to its expense. Called inside the transaction that links the debit,
     * after the link; a commission that is not there yet joins when it arrives (`forNew`).
     *
     * @param  BankTransaction  $debit  the debit, linked
     * @param  BankFilter|null  $filter  the filter that took the debit, which says how its commission is found
     */
    public function forDebit(BankTransaction $debit, ?BankFilter $filter = null): void
    {
        if ($debit->expense_id === null || $debit->matched_by === BankMatchSource::Fee || BankText::isFee($debit->text)) {
            return;
        }

        $fee = $this->fees->feeOf($debit, $filter);
        $expense = $fee instanceof BankTransaction ? Expense::query()->find($debit->expense_id) : null;

        if ($fee instanceof BankTransaction && $expense instanceof Expense) {
            $this->handle($fee, $expense, $debit);
        }
    }

    /**
     * Joins the new commissions of a read to the expense of their debit when it is already linked: a commission of
     * the mandate kind to its debit (`debitOf()`) when the filter of the debit finds it by itself, otherwise any new transaction that the commission words of the
     * filter of a linked debit take (`debitByWords()`). A failure is reported and the others go on: the read of the
     * bank never fails for it.
     *
     * @param  iterable<BankTransaction>  $new  the transactions just saved
     */
    public function forNew(iterable $new): void
    {
        foreach ($new as $transaction) {
            try {
                $debit = BankText::isFee($transaction->text) ? $this->fees->debitOf($transaction, includeSorted: true) : null;
                // The filter of the debit decides, as in `forDebit()`: one with no commission takes none, one with
                // words takes only what its words take (`debitByWords()` below)
                $debit = $debit instanceof BankTransaction && $this->findsByItself($debit) ? $debit : null;
                $expense = $this->expenseOf($debit);

                if (! $expense instanceof Expense) {
                    $debit = $this->fees->debitByWords($transaction);
                    $expense = $this->expenseOf($debit);
                }

                if ($expense instanceof Expense) {
                    $this->handle($transaction, $expense, $debit);
                }
            } catch (Throwable $failure) {
                report($failure);
            }
        }
    }

    /**
     * Whether the commission of a debit is found by itself (the same mandate and creditor): the debit has no filter,
     * or its filter says `auto`.
     *
     * @param  BankTransaction  $debit  the debit
     */
    private function findsByItself(BankTransaction $debit): bool
    {
        return $debit->bank_filter_id === null || ($debit->bankFilter->fee_mode ?? BankFilterFee::Auto) === BankFilterFee::Auto;
    }

    /**
     * The expense a debit is linked to, unless the debit is itself a commission.
     *
     * @param  BankTransaction|null  $debit  the debit
     */
    private function expenseOf(?BankTransaction $debit): ?Expense
    {
        return $debit?->expense_id !== null && $debit->matched_by !== BankMatchSource::Fee ? Expense::query()->find($debit->expense_id) : null;
    }
}
