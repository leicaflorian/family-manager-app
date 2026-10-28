<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A card that can be added already linked to the one on the page, as the +
 * of *Linked to* offers it.
 */
#[TypeScript]
final class CardAddData extends Data
{
    /**
     * @param  string  $type  the type of the new card, as the morph map writes it (`maintenance_record`)
     * @param  string  $label  the name of the type, in the current language
     * @param  string  $href  the address of its form, with the link already filled in
     */
    public function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly string $href,
    ) {}
}
