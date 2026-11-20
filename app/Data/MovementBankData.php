<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\BankMatchSource;
use App\Models\BankTransaction;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The bank transaction an expense or income was recorded from, for the line «Registrata dalla banca» of its page.
 */
#[TypeScript]
final class MovementBankData extends Data
{
    /**
     * @param  string|null  $booked_on  the day the bank booked it (`yyyy-mm-dd`)
     * @param  string  $text  the description as the bank wrote it
     * @param  string|null  $account  the account, as «Account name ·· 1234»
     * @param  string|null  $undo_url  where *It was not this one* goes, only for a payment the bank recorded and for who can delete the movement
     */
    public function __construct(
        public readonly ?string $booked_on,
        public readonly string $text,
        public readonly ?string $account,
        public readonly ?string $undo_url,
    ) {}

    /**
     * The data of a transaction linked to a movement.
     *
     * @param  BankTransaction  $transaction  the transaction, with its account loaded
     * @param  bool  $canUndo  whether the person can delete the movement
     */
    public static function fromTransaction(BankTransaction $transaction, bool $canUndo): self
    {
        $account = $transaction->financialAccount;
        $recorded = in_array($transaction->matched_by, [BankMatchSource::Rule, BankMatchSource::Assigned], true);

        return new self(
            $transaction->booked_on?->toDateString(),
            $transaction->text,
            $account->last_four_digits === null ? $account->name : $account->name.' ·· '.$account->last_four_digits,
            $recorded && $canUndo ? route('budget.bank.undo', ['transaction' => $transaction->id], absolute: false) : null,
        );
    }
}
