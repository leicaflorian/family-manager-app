<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A record a document links to, as *A document* lists it.
 */
#[TypeScript]
final class DocumentLinkData extends Data
{
    /**
     * @param  string  $type  the type, as the morph map writes it (`employment`)
     * @param  int  $id  the id of the record
     * @param  string  $type_label  the name of the type, in the current language
     * @param  string  $label  the name of the record
     * @param  string|null  $href  the address of its card page, null for a record without one (an expense, an income)
     */
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly string $type_label,
        public readonly string $label,
        public readonly ?string $href = null,
    ) {}
}
