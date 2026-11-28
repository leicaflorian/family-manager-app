<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Models\BankTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Puts a bank transaction back on the list to sort: takes away the mark of *Ignore*, or the transfer on both halves.
 */
final readonly class RestoreBankTransaction
{
    /**
     * Restores the transaction. An ignored one loses `ignored_at`; a transfer loses `transfer_account_id` and
     * `transfer_pair_id`, and so does its other half when it has one. `declined_at` stays as it is, so no rule takes
     * it again by itself; a transaction that a filter ignored or made a transfer (`bank_filter_id`) is declined now and
     * forgets the filter, so no filter takes it again either. The transaction is locked first, then its other half.
     *
     * @param  BankTransaction  $transaction  the transaction to put back
     */
    public function handle(BankTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $current = BankTransaction::query()->whereKey($transaction->id)->lockForUpdate()->first();

            if ($current === null) {
                return;
            }

            if ($current->transfer_account_id === null && $current->transfer_pair_id === null) {
                $current->forceFill(['ignored_at' => null, ...$this->declineFilter($current)])->save();

                return;
            }

            $pair = $current->transfer_pair_id !== null
                ? BankTransaction::query()->whereKey($current->transfer_pair_id)->lockForUpdate()->first()
                : null;

            foreach ([$current, $pair] as $half) {
                $half?->forceFill(['transfer_account_id' => null, 'transfer_pair_id' => null, ...$this->declineFilter($half)])->save();
            }
        });
    }

    /**
     * What to write so that no filter takes the transaction again, when a filter had it.
     *
     * @param  BankTransaction  $transaction  the transaction put back
     * @return array<string, mixed> empty when no filter had it
     */
    private function declineFilter(BankTransaction $transaction): array
    {
        return $transaction->bank_filter_id === null ? [] : ['bank_filter_id' => null, 'declined_at' => $transaction->declined_at ?? now()];
    }
}
