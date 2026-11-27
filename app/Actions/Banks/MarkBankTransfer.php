<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Models\BankTransaction;
use App\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marks a bank transaction as a transfer between two accounts of the family: it is not an expense nor an income, it
 * leaves the list to sort and no budget figure sees it. When both accounts are read from the bank, the two halves of
 * the transfer are paired, whichever arrives first.
 */
final readonly class MarkBankTransfer
{
    /** How many days apart the two halves can be booked. */
    private const int DAYS = 3;

    /**
     * Marks the transaction as a transfer to or from `$other` and pairs it with its other half when that is already
     * there. The transaction is locked first, then the other half. With `$declined` a rule or a filter never takes the
     * transaction again once the person puts it back (`declined_at`).
     *
     * @param  BankTransaction  $transaction  the transaction, still to sort
     * @param  FinancialAccount  $other  the account on the other side
     * @param  bool  $declined  the person said no to a proposal, so no rule or filter takes it again
     *
     * @throws ValidationException (`account_id`) if `$other` is the account of the transaction, (`bank_transaction_id`) if it is not to sort any more
     */
    public function handle(BankTransaction $transaction, FinancialAccount $other, bool $declined = false): void
    {
        if ($other->id === $transaction->financial_account_id) {
            throw ValidationException::withMessages(['account_id' => __('Choose another account: this movement is already on this one.')]);
        }

        DB::transaction(function () use ($transaction, $other, $declined): void {
            $current = BankTransaction::query()->unsorted()->whereKey($transaction->id)->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(['bank_transaction_id' => __('This movement from the bank is not to sort any more.')]);
            $half = $this->halfOf($current, $other, lock: true);

            $current->forceFill([
                'transfer_account_id' => $other->id,
                'transfer_pair_id' => $half?->id,
                ...($declined ? ['declined_at' => now()] : []),
            ])->save();
            $half?->forceFill(['transfer_account_id' => $current->financial_account_id, 'transfer_pair_id' => $current->id])->save();
        });

        $transaction->refresh();
    }

    /**
     * The other half of a transfer: a transaction still to sort on `$other`, the opposite way, of the same amount (in
     * cents), booked within three days before or after, the closest (the oldest row on a tie; whole days are rounded, a clock change makes one a few minutes short).
     *
     * @param  BankTransaction  $transaction  the first half
     * @param  FinancialAccount  $other  the account the other half is on
     * @param  bool  $lock  lock the row found (inside the transaction that marks it)
     */
    public function halfOf(BankTransaction $transaction, FinancialAccount $other, bool $lock = false): ?BankTransaction
    {
        return $this->closest(
            BankTransaction::query()->unsorted()->where('financial_account_id', $other->id),
            $transaction,
            $lock,
        );
    }

    /**
     * Pairs the new transactions with a transfer already marked on another account towards theirs (the second half
     * arrives at a later read): both get `transfer_pair_id`, the new one is a transfer from the account of the first.
     *
     * @param  iterable<BankTransaction>  $new  the booked transactions a read has just saved
     * @return int how many were paired
     */
    public function pairNew(iterable $new): int
    {
        $paired = 0;

        foreach ($new as $transaction) {
            DB::transaction(function () use ($transaction, &$paired): void {
                $current = BankTransaction::query()->unsorted()->whereKey($transaction->id)->lockForUpdate()->first();

                if ($current === null) {
                    return;
                }

                $first = $this->closest(
                    BankTransaction::query()
                        ->whereNotNull('transfer_account_id')
                        ->whereNull('transfer_pair_id')
                        ->where('transfer_account_id', $current->financial_account_id)
                        ->where('financial_account_id', '!=', $current->financial_account_id),
                    $current,
                    lock: true,
                );

                if (! $first instanceof BankTransaction) {
                    return;
                }

                $current->forceFill(['transfer_account_id' => $first->financial_account_id, 'transfer_pair_id' => $first->id])->save();
                $first->forceFill(['transfer_pair_id' => $current->id])->save();
                $paired++;
            });
        }

        return $paired;
    }

    /**
     * The row of the query that is the opposite of the transaction: not the transaction itself, the opposite sign, the
     * same amount in cents, booked within three days, the closest.
     *
     * @param  Builder<BankTransaction>  $query  the rows that could be the other half
     * @param  BankTransaction  $transaction  the transaction
     * @param  bool  $lock  lock the rows read
     */
    private function closest(Builder $query, BankTransaction $transaction, bool $lock): ?BankTransaction
    {
        if ($transaction->booked_on === null) {
            return null;
        }

        $cents = (int) round((float) $transaction->amount * 100);

        if ($cents === 0) {
            return null;
        }

        $query->whereKeyNot($transaction->id)
            ->whereRaw('round(amount * 100) = ?', [-$cents])
            ->whereBetween('booked_on', [$transaction->booked_on->subDays(self::DAYS)->toDateString(), $transaction->booked_on->addDays(self::DAYS)->toDateString()])
            ->orderBy('id');

        $rows = $lock ? $query->lockForUpdate()->get() : $query->get();

        return $rows->sortBy(fn (BankTransaction $row): int => (int) round(abs($row->booked_on?->diffInDays($transaction->booked_on, false) ?? 0)))->first();
    }
}
