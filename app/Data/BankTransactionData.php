<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\BankTransactionStatus;
use App\Models\BankTransaction;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A transaction read from the bank, as *From the bank* lists it.
 */
#[TypeScript]
final class BankTransactionData extends Data
{
    /**
     * @param  int  $id  the transaction
     * @param  string|null  $booked_on  the day the bank booked it (`yyyy-mm-dd`); null while it is pending
     * @param  string|null  $value_on  the day the bank gave for it (`yyyy-mm-dd`), the one a pending transaction is shown with
     * @param  string  $text  the description as the bank wrote it
     * @param  string|null  $counterparty  who paid or was paid
     * @param  float  $amount  the amount with the sign: negative is a payment
     * @param  string  $currency  the currency, `EUR` for the ones that can be sorted
     * @param  string|null  $account  the account, as «Account name ·· 1234»
     * @param  bool  $pending  the bank has not booked it yet
     * @param  bool  $ignored  the person put it aside
     * @param  bool  $recurring  its text comes back every month, so a planned expense can be offered (only for a booked, unsorted one)
     */
    public function __construct(
        public readonly int $id,
        public readonly ?string $booked_on,
        public readonly ?string $value_on,
        public readonly string $text,
        public readonly ?string $counterparty,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $account,
        public readonly bool $pending,
        public readonly bool $ignored,
        public readonly bool $recurring,
    ) {}

    /**
     * The data of a transaction of the current family.
     *
     * @param  BankTransaction  $transaction  the transaction, with its account loaded
     * @param  bool  $recurring  its text comes back every month
     */
    public static function fromTransaction(BankTransaction $transaction, bool $recurring = false): self
    {
        $account = $transaction->financialAccount;

        return new self(
            $transaction->id,
            $transaction->booked_on?->toDateString(),
            $transaction->value_on?->toDateString(),
            $transaction->text,
            $transaction->counterparty,
            round((float) $transaction->amount, 2),
            $transaction->currency,
            $account->last_four_digits === null ? $account->name : $account->name.' ·· '.$account->last_four_digits,
            $transaction->status === BankTransactionStatus::Pending,
            $transaction->ignored_at !== null,
            $recurring,
        );
    }
}
