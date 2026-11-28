<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A movement of a group to sort, with what the proposal of the group would do with it.
 */
#[TypeScript]
final class BankGroupRowData extends Data
{
    /**
     * @param  BankTransactionData  $transaction  the movement
     * @param  BankFeeData|null  $fee  the commission that goes with it, when it is a direct debit with one
     * @param  string  $will  what saving the proposal does: `pay`, `create`, `link`, `transfer`, `ignore` or `none` (no proposal, or it stays to sort)
     * @param  string|null  $will_title  the due date it pays, the expense it creates or links
     * @param  string|null  $will_on  the day of the expense it links or of the due date it pays (`yyyy-mm-dd`)
     */
    public function __construct(
        public readonly BankTransactionData $transaction,
        public readonly ?BankFeeData $fee,
        public readonly string $will,
        public readonly ?string $will_title,
        public readonly ?string $will_on,
    ) {}
}
