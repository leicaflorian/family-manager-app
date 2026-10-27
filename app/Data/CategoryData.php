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
     * @param  int  $documents_count  how many documents it holds, without those of the subcategories
     * @param  list<CategoryData>  $children  the subcategories, in order
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?int $parent_id,
        public readonly ?string $icon,
        public readonly ?string $color,
        public readonly int $documents_count,
        public readonly array $children,
    ) {}
}
