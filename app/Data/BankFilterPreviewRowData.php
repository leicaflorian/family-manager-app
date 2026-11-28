<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A movement of the bank in the preview of a filter: the movement, the words of the filter found in its text, its
 * commission, what saving the filter would do with it and the other filter that takes it already.
 */
#[TypeScript]
final class BankFilterPreviewRowData extends Data
{
    /**
     * @param  BankTransactionData  $transaction  the movement, with what it is linked to
     * @param  list<string>  $hits  the words of the filter found in its text, normalised, to highlight them
     * @param  BankFeeData|null  $fee  the commission found as the filter says, null when there is none
     * @param  string  $will  what saving does: `pay`, `create`, `link`, `transfer`, `ignore`, `linked` (already sorted, never touched) or `none` (it stays to sort)
     * @param  string|null  $will_title  the due date it pays, the movement it creates or links, or the account of the transfer
     * @param  string|null  $taken_by  the name of the other filter that takes it already, while it is to sort: two filters on one movement do not save
     * @param  string|null  $will_on  the day of the movement it links or of the due date it pays (`yyyy-mm-dd`)
     * @param  bool  $to_sort  the movement is still among the ones to sort by hand: not linked, not put aside, not a transfer and not a commission
     */
    public function __construct(
        public readonly BankTransactionData $transaction,
        public readonly array $hits,
        public readonly ?BankFeeData $fee,
        public readonly string $will,
        public readonly ?string $will_title,
        public readonly ?string $taken_by,
        public readonly ?string $will_on = null,
        public readonly bool $to_sort = false,
    ) {}
}
