<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The records of one type a document can link to, for the menu of the links.
 */
#[TypeScript]
final class LinkGroupData extends Data
{
    /**
     * @param  string  $type  the type, as the morph map writes it (`employment`)
     * @param  string  $label  the name of the type, in the current language
     * @param  list<OptionData>  $options  the records: the id as value, the name as label
     */
    public function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly array $options,
    ) {}
}
