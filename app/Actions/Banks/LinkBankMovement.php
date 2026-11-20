<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Enums\BankMatchSource;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use Illuminate\Validation\ValidationException;

/**
 * *Create the expense* from a bank transaction: links the transaction to the expense or income the person has just
 * created from it. Both calls run inside the transaction that saves the movement: `lock()` first, before anything
 * else is read or written (the bank row comes before the planned item everywhere, so this and a settling wait for
 * each other), then `handle()` once the movement is saved.
 */
final readonly class LinkBankMovement
{
    /**
     * Locks the transaction and checks it is still to sort, as the first query of the transaction that saves the
     * movement: a rule or a settling that got there first leaves nothing to create a second expense for.
     *
     * @param  int  $id  The transaction.
     * @return BankTransaction the transaction, locked
     *
     * @throws ValidationException (`bank_transaction_id`) if it is not to sort any more
     */
    public function lock(int $id): BankTransaction
    {
        return BankTransaction::query()->unsorted()->whereKey($id)->lockForUpdate()->first()
            ?? throw ValidationException::withMessages(['bank_transaction_id' => __('This movement from the bank is not to sort any more.')]);
    }

    /**
     * Links the transaction to the movement as created by the person. The row is locked again and read again, so a
     * call without `lock()` is as safe.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @param  Expense|Income  $movement  The movement just saved.
     * @param  BankMatchSource  $by  How the link came: `Created`.
     *
     * @throws ValidationException (`bank_transaction_id`) if the transaction is not to sort any more or is of the other kind
     */
    public function handle(BankTransaction $transaction, Expense|Income $movement, BankMatchSource $by): void
    {
        $current = $this->lock($transaction->id);

        if (($movement instanceof Expense ? 'expense' : 'income') !== $current->kind()) {
            throw ValidationException::withMessages(['bank_transaction_id' => __('This movement from the bank is not to sort any more.')]);
        }

        $current->linkTo($movement, $by);
    }
}
