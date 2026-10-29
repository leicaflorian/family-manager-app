<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A figure of the payments of a card, as a tile of its page: *Planned 72,00 €, per month, estimated*.
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
     */
    public function __construct(
        public readonly string $label,
        public readonly ?float $amount,
        public readonly ?int $count = null,
        public readonly ?string $hint = null,
        public readonly ?float $hint_amount = null,
    ) {}
}
