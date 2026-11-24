<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A row of the section *Deadlines* of a vehicle: a planned expense with its next due date to pay, a date of the card
 * still without its planned expense, or a date of the card that is missing.
 */
#[TypeScript]
final class CardDeadlineData extends Data
{
    /**
     * @param  string  $label  what falls due, for example «Carburante» or «Inspection»
     * @param  string|null  $date  the day, `yyyy-mm-dd`; null when the card misses it
     * @param  string|null  $detail  how often it repeats or the policy it belongs to, for example «every month» or «RC auto Panda»
     * @param  float|null  $amount  the amount of the due date, null without a planned expense or with the Budget off
     * @param  bool  $estimated  whether the amount is an estimate
     * @param  string|null  $href  the page of the planned expense (with the Budget off, of the policy); null for a date of the card
     * @param  string|null  $add_expense_href  *Add the expense*: the new planned expense already filled in; null when not missing, with the Budget off or for whoever may not create one
     * @param  string|null  $add_date_href  *Add the date*: the form of the card; null when the date is there or for whoever may not edit the card
     */
    public function __construct(
        public readonly string $label,
        public readonly ?string $date,
        public readonly ?string $detail = null,
        public readonly ?float $amount = null,
        public readonly bool $estimated = false,
        public readonly ?string $href = null,
        public readonly ?string $add_expense_href = null,
        public readonly ?string $add_date_href = null,
    ) {}
}
