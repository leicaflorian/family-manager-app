<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A document category in the tree, with its subcategories.
 */
#[TypeScript]
final class CategoryData extends Data
{
    /**
     * @param  int  $id  the id
     * @param  string  $name  the name
     * @param  int|null  $parent_id  the category it sits in, null for a main one
     * @param  string|null  $icon  the icon, a Lucide name
     * @param  string|null  $color  the colour, an area; a subcategory takes the one of its main category
     * @param  string|null  $card_type  the card its documents go with, as in the morph map; its own, not inherited
     * @param  bool  $gallery  whether it is the drawings gallery
     * @param  string|null  $inherited_card_type  the card of its main category, null for a main one
     * @param  int  $documents_count  how many documents it holds, without those of the subcategories
     * @param  list<CategoryData>  $children  the subcategories, in order
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?int $parent_id,
        public readonly ?string $icon,
        public readonly ?string $color,
        public readonly ?string $card_type,
        public readonly bool $gallery,
        public readonly ?string $inherited_card_type,
        public readonly int $documents_count,
        public readonly array $children,
    ) {}
}
