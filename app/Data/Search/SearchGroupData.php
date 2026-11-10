<?php

declare(strict_types=1);

namespace App\Data\Search;

use App\Enums\SearchGroup;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A group of search results with its name and how many results it has in all.
 */
#[TypeScript]
final class SearchGroupData extends Data
{
    /**
     * @param  string  $label  the translated name of the group
     * @param  int  $count  how many results the group has, not only the ones listed
     * @param  list<SearchResultData>  $results  the results listed
     */
    public function __construct(
        public readonly SearchGroup $group,
        public readonly string $label,
        public readonly int $count,
        #[DataCollectionOf(SearchResultData::class)]
        public readonly array $results,
    ) {}
}
