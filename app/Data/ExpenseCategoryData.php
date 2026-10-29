<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An expense category in the tree of *Expense categories*, with the card its expenses refer to and its subcategories.
 */
#[TypeScript]
final class ExpenseCategoryData extends Data
{
    /**
     * @param  int  $id  the id
     * @param  string  $name  the name
     * @param  int|null  $parent_id  the category it sits in, null for a main one
     * @param  string|null  $icon  the icon, a Lucide name
     * @param  string|null  $color  the colour; a subcategory takes the one of its main category
     * @param  string|null  $card_type  the card type its expenses refer to (a type of the morph map), inherited from the main category
     * @param  string|null  $card_label  the name of that card type, for example «Vehicle»
     * @param  list<ExpenseCategoryData>  $children  the subcategories, in order
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?int $parent_id,
        public readonly ?string $icon,
        public readonly ?string $color,
        public readonly ?string $card_type,
        public readonly ?string $card_label,
        public readonly array $children,
    ) {}
}
