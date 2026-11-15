<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A figure of a card, as a tile of its page: *Planned 72,00 €, per month, estimated*, or a word such as
 * the status, *Status Active*, in the colour of its tone.
 */
#[TypeScript]
final class CardNumberData extends Data
{
    /**
     * @param  string  $label  what the figure is, for example «Planned»
     * @param  float|null  $amount  the amount, when the figure is one
     * @param  int|null  $count  the number, when the figure is not an amount (*Utilities 4*)
     * @param  string|null  $hint  the line below, for example «per month, estimated»; it may hold `:amount`
     * @param  float|null  $hint_amount  the amount the page writes in place of `:amount` in the hint
     * @param  string|null  $text  the word, when the figure is neither an amount nor a number (*Active*)
     * @param  'open'|'overdue'|'closed'|null  $tone  the colour of the word: green, red or grey
     */
    public function __construct(
        public readonly string $label,
        public readonly ?float $amount,
        public readonly ?int $count = null,
        public readonly ?string $hint = null,
        public readonly ?float $hint_amount = null,
        public readonly ?string $text = null,
        #[LiteralTypeScriptType("'open' | 'overdue' | 'closed' | null")]
        public readonly ?string $tone = null,
    ) {}
}
