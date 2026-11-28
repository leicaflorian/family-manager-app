<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Support\Banks\BankUndo;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The bank transaction an expense or income was recorded from, for the box «Registrata dalla banca» of its page: the
 * text of the bank, how it was linked, the commission joined to it, the filter that took it and the way to take the
 * link back.
 */
#[TypeScript]
final class MovementBankData extends Data
{
    /**
     * @param  string|null  $booked_on  the day the bank booked it (`yyyy-mm-dd`)
     * @param  string  $text  the description as the bank wrote it
     * @param  string|null  $account  the account, as «Account name ·· 1234»
     * @param  string|null  $undo_url  where *It was not this one* goes, only when `undo` is given
     * @param  string  $how  how the link came (a `BankMatchSource` value)
     * @param  BankFeeData|null  $fee  the commission of its direct debit joined to the expense
     * @param  array{id: int, name: string, url: string|null}|null  $filter  the filter that took it; `url` is the address of the filter's own page
     * @param  string  $url  the transaction in the list *All* of *From the bank* (`?show=all` and its text in `q`)
     * @param  string|null  $undo  what *It was not this one* does: `delete` (the movement goes) or `detach` (it only comes off a movement that was already there); null when it cannot be undone or the person may not
     */
    public function __construct(
        public readonly ?string $booked_on,
        public readonly string $text,
        public readonly ?string $account,
        public readonly ?string $undo_url,
        public readonly string $how,
        public readonly ?BankFeeData $fee,
        public readonly ?array $filter,
        public readonly string $url,
        public readonly ?string $undo,
    ) {}

    /**
     * The data of a transaction linked to a movement.
     *
     * @param  BankTransaction  $transaction  the transaction, with its account and filter loaded
     * @param  Expense|Income  $movement  the movement it is linked to
     * @param  BankTransaction|null  $fee  the commission joined to the movement, for an expense
     */
    public static function fromTransaction(BankTransaction $transaction, Expense|Income $movement, ?BankTransaction $fee = null): self
    {
        $account = $transaction->financialAccount;
        $undo = BankUndo::of($transaction, $movement);

        return new self(
            $transaction->booked_on?->toDateString(),
            $transaction->text,
            $account->last_four_digits === null ? $account->name : $account->name.' ·· '.$account->last_four_digits,
            $undo !== null ? route('budget.bank.undo', ['transaction' => $transaction->id], absolute: false) : null,
            $transaction->matched_by->value ?? 'duplicate',
            $fee instanceof BankTransaction ? BankFeeData::fromTransaction($fee) : null,
            $transaction->bankFilter === null ? null : ['id' => $transaction->bankFilter->id, 'name' => $transaction->bankFilter->name, 'url' => route('budget.bank.filters.edit', ['filter' => $transaction->bankFilter->id], absolute: false)],
            route('budget.bank.index', ['show' => 'all', 'q' => mb_substr($transaction->text, 0, 100)], absolute: false),
            $undo,
        );
    }
}
