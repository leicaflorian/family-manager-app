<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A piece of the line under the name of a card, with the address of the
 * person or of the organization it names.
 */
#[TypeScript]
final class CardLinkData extends Data
{
    /**
     * @param  string  $text  the piece as the person reads it, for example «Studio Ferri»
     * @param  string|null  $href  the address of the person or of the organization, when the piece names one
     */
    public function __construct(
        public readonly string $text,
        public readonly ?string $href = null,
    ) {}
}
