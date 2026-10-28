<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A line of the data of a card: the label and the value as the person reads it.
 */
#[TypeScript]
final class CardValueData extends Data
{
    /**
     * @param  string  $label  the label of the field
     * @param  string  $text  the value, for example `15/10/2026` or `84,20 €`
     * @param  string|null  $href  the address of the person or of the organization, when the value is one
     */
    public function __construct(
        public readonly string $label,
        public readonly string $text,
        public readonly ?string $href = null,
    ) {}
}
