<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\BankMatchSource;
use App\Enums\BankRowStatus;
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
     * @param  string  $status  what it is in the list *All* (a `BankRowStatus` value)
     * @param  BankLinkData|null  $link  the expense or income it is linked to
     * @param  string  $provider_id  the id the bank gave it
     * @param  string  $read_at  when it was read from the bank (`yyyy-mm-ddThh:mm`, Rome)
     * @param  string  $bank_status  whether the bank booked it (a `BankTransactionStatus` value)
     * @param  BankFeeData|null  $fee  the commission the bank charged for this direct debit, shown under it
     * @param  bool  $orphan_fee  the row is a commission whose direct debit is not among the ones to sort
     * @param  BankTransferData|null  $transfer  the account on the other side, when it is a transfer between the family's own accounts
     * @param  int|null  $account_id  the account of the transaction (`financial_account_id`)
     * @param  BankProposalData|null  $proposal  «Maybe it is…»: what the system proposes a lone movement to sort is
     * @param  list<string>|null  $two_filters  the names of the filters that all take it, when two or more do: none of them settles it
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
        public readonly string $status,
        public readonly ?BankLinkData $link,
        public readonly string $provider_id,
        public readonly string $read_at,
        public readonly string $bank_status,
        public readonly ?BankFeeData $fee = null,
        public readonly bool $orphan_fee = false,
        public readonly ?BankTransferData $transfer = null,
        public readonly ?int $account_id = null,
        public readonly ?BankProposalData $proposal = null,
        public readonly ?array $two_filters = null,
    ) {}

    /**
     * The data of a transaction of the current family.
     *
     * @param  BankTransaction  $transaction  the transaction, with its account (and the account of its transfer) loaded
     * @param  bool  $recurring  its text comes back every month
     * @param  bool  $withLink  whether to read the expense or income it is linked to (the list *All* does; they must be loaded)
     * @param  BankTransaction|null  $fee  the commission paired with this direct debit
     * @param  bool  $orphanFee  this is a commission whose direct debit is not to sort
     * @param  BankProposalData|null  $proposal  «Maybe it is…» of a lone movement to sort (`BankProposals`)
     * @param  list<string>|null  $twoFilters  the names of the filters that take it, when two or more do
     */
    public static function fromTransaction(BankTransaction $transaction, bool $recurring = false, bool $withLink = false, ?BankTransaction $fee = null, bool $orphanFee = false, ?BankProposalData $proposal = null, ?array $twoFilters = null): self
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
            self::statusOf($transaction)->value,
            $withLink ? BankLinkData::fromTransaction($transaction) : null,
            $transaction->provider_id,
            ($transaction->created_at ?? now())->setTimezone('Europe/Rome')->format('Y-m-d\TH:i'),
            $transaction->status->value,
            $fee instanceof BankTransaction ? BankFeeData::fromTransaction($fee) : null,
            $orphanFee,
            $transaction->transfer_account_id === null ? null : BankTransferData::fromTransaction($transaction),
            $transaction->financial_account_id,
            $proposal,
            $twoFilters,
        );
    }

    /**
     * What the transaction is: pending while the bank has not booked it, then a transfer, put aside, a commission joined
     * to the expense of its debit, linked, else to sort.
     *
     * @param  BankTransaction  $transaction  the transaction
     */
    public static function statusOf(BankTransaction $transaction): BankRowStatus
    {
        return match (true) {
            $transaction->status === BankTransactionStatus::Pending => BankRowStatus::Pending,
            $transaction->transfer_account_id !== null => BankRowStatus::Transfer,
            $transaction->ignored_at !== null => BankRowStatus::Ignored,
            $transaction->matched_by === BankMatchSource::Fee && $transaction->expense_id !== null => BankRowStatus::Fee,
            $transaction->expense_id !== null || $transaction->income_id !== null => BankRowStatus::Linked,
            default => BankRowStatus::Unsorted,
        };
    }
}
