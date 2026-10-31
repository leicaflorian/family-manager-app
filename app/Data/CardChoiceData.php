<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A card in the menu *Refers to* of an expense or a planned expense, with what the form takes from it when it is
 * chosen. The ids are text, as the menus of FormKit compare them.
 */
#[TypeScript]
final class CardChoiceData extends Data
{
    /**
     * @param  string  $value  the card, `type:id`
     * @param  string  $label  «Type · name»
     * @param  string|null  $person_id  the holder of the card
     * @param  string|null  $organization_id  the organization of the card
     * @param  string|null  $category_id  the first main category whose expenses refer to cards of its type
     */
    public function __construct(
        public readonly string $value,
        public readonly string $label,
        public readonly ?string $person_id = null,
        public readonly ?string $organization_id = null,
        public readonly ?string $category_id = null,
    ) {}
}
