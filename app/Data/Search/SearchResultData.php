<?php

declare(strict_types=1);

namespace App\Data\Search;

use App\Enums\SearchGroup;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One result of the global search.
 */
#[TypeScript]
final class SearchResultData extends Data
{
    /**
     * @param  string  $key  unique in the page, for example `document-12`
     * @param  string  $title  the name of the result
     * @param  string|null  $subtitle  the line under the title
     * @param  string|null  $date  `yyyy-mm-dd`, written by the page inside the subtitle
     * @param  float|null  $amount  the amount of a movement or a due date
     * @param  string  $href  where the result opens
     * @param  string|null  $area  the value of an `App\Enums\Area`; the page then takes icon and colour from the area
     * @param  string|null  $icon  a Lucide icon in kebab case, set when there is no area
     * @param  string|null  $tone  `neutral`, `expenses`, `income` or `action`, set when there is no area
     * @param  string|null  $flag  an already translated tag such as *Archived* or *Closed*, or null
     * @param  string|null  $due_in  the due date (`yyyy-mm-dd`) of a deadline, for «in 15 days», or null
     */
    public function __construct(
        public readonly string $key,
        public readonly SearchGroup $group,
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly ?string $date,
        public readonly ?float $amount,
        public readonly string $href,
        public readonly ?string $area,
        public readonly ?string $icon,
        public readonly ?string $tone,
        public readonly ?string $flag,
        public readonly ?string $due_in,
    ) {}
}
