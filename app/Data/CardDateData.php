<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A date in the tiles of a card.
 */
#[TypeScript]
final class CardDateData extends Data
{
    /**
     * @param  string  $label  the label of the date
     * @param  string  $date  the date, as `yyyy-mm-dd`
     * @param  bool  $expires  whether the date is a deadline
     */
    public function __construct(
        public readonly string $label,
        public readonly string $date,
        public readonly bool $expires,
    ) {}
}
