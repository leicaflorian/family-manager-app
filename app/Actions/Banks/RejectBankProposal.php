<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Models\BankTransaction;
use Illuminate\Support\Facades\DB;

/**
 * *No* on «Maybe it is…»: the movement remembers the key it was given, so that proposal does not come back and the
 * next candidate can be proposed.
 */
final readonly class RejectBankProposal
{
    /**
     * Adds the key to `rejected_proposals` as a value of the list, once. The row is locked, so two *No* at the same
     * moment both stay.
     *
     * @param  BankTransaction  $transaction  the movement of the bank
     * @param  string  $key  the key of the proposal
     */
    public function handle(BankTransaction $transaction, string $key): void
    {
        DB::transaction(function () use ($transaction, $key): void {
            $current = BankTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $rejected = $current->rejected_proposals ?? [];

            if (array_key_exists($key, $rejected) || in_array($key, $rejected, true)) {
                return;
            }

            $rejected[] = $key;
            $current->forceFill(['rejected_proposals' => $rejected])->save();
        });

        $transaction->refresh();
    }
}
