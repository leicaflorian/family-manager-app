<?php

declare(strict_types=1);

namespace App\Cards;

use BackedEnum;

/**
 * A field of a card type: the column it fills, its label, its kind and when
 * the form asks it.
 */
final readonly class CardField
{
    /**
     * @param  string  $column  the column of the table, or the name of a virtual field
     * @param  string  $label  the label, already translated
     * @param  FieldKind  $kind  the kind of field
     * @param  bool  $required  whether the person has to fill it in (when it is shown)
     * @param  class-string<BackedEnum>|null  $enum  the choices, for `choice` and `dropdown`
     * @param  string|null  $target  the type of the morph map of the linked card, for `card`
     * @param  array<string, list<string>>  $showWhen  the field is shown only when each column has one of these values
     * @param  bool  $virtual  whether the field is not a column of the table
     */
    public function __construct(
        public string $column,
        public string $label,
        public FieldKind $kind,
        public bool $required = false,
        public ?string $enum = null,
        public ?string $target = null,
        public array $showWhen = [],
        public bool $virtual = false,
    ) {}
}
