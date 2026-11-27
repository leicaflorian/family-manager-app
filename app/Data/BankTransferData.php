<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\BankTransaction;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a bank transaction says when it is a transfer between the family's own accounts: the account on the other
 * side, which way the money went and whether the other half is on the list too.
 */
#[TypeScript]
final class BankTransferData extends Data
{
    /**
     * @param  string  $account  the name of the account on the other side
     * @param  string  $direction  `to` when the money left this account for the other, `from` when it came from there
     * @param  bool  $paired  the other half was read from the bank and is linked
     */
    public function __construct(
        public readonly string $account,
        public readonly string $direction,
        public readonly bool $paired,
    ) {}

    /**
     * The transfer of a transaction. It reads `transferAccount`, so the caller loads it.
     *
     * @param  BankTransaction  $transaction  a transaction with `transfer_account_id`
     */
    public static function fromTransaction(BankTransaction $transaction): self
    {
        return new self(
            account: $transaction->transferAccount->name ?? '',
            direction: (float) $transaction->amount < 0 ? 'to' : 'from',
            paired: $transaction->transfer_pair_id !== null,
        );
    }
}
