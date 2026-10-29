<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A row of *By category* in *Planned and spent*: a main expense category with the figures of its subcategories added.
 */
#[TypeScript]
final class CategoryComparisonData extends Data
{
    /**
     * @param  string  $name  the name of the main category, or «Uncategorised» for the expenses without one
     * @param  string|null  $icon  the Lucide icon of the category
     * @param  string|null  $color  the colour of the category
     * @param  float  $spent  what was spent in the month, subcategories included
     * @param  float  $planned  the due dates of the month, subcategories included
     * @param  bool  $estimated  whether every due date of the category is an estimate
     * @param  string|null  $late_title  the title of the planned expense whose due date is late, if there is one
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $icon,
        public readonly ?string $color,
        public readonly float $spent,
        public readonly float $planned,
        public readonly bool $estimated,
        public readonly ?string $late_title,
    ) {}
}
