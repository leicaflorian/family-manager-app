<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A field of the form of a card, as the page draws it.
 */
#[TypeScript]
final class CardFieldData extends Data
{
    /**
     * @param  string  $column  the name of the field in the form
     * @param  string  $label  the label
     * @param  string  $kind  the kind of input (`text`, `date`, `choice`, ...)
     * @param  bool  $required  whether the person has to fill it in
     * @param  list<OptionData>  $options  the choices, for the kinds that have them
     * @param  array<string, list<string>>  $show_when  the field is shown only when each field has one of these values
     * @param  array<string, array<string, string>>  $label_when  another label when a field has one of these values
     */
    public function __construct(
        public readonly string $column,
        public readonly string $label,
        public readonly string $kind,
        public readonly bool $required,
        #[DataCollectionOf(OptionData::class)]
        public readonly array $options,
        public readonly array $show_when,
        public readonly array $label_when,
    ) {}
}
