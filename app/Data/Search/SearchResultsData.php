<?php

declare(strict_types=1);

namespace App\Data\Search;

use App\Enums\SearchGroup;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The answer of the global search: the text, the group it was narrowed to, and the groups that found something.
 */
#[TypeScript]
final class SearchResultsData extends Data
{
    /**
     * @param  string  $query  the clean text that was searched
     * @param  SearchGroup|null  $type  the group the search was narrowed to, or null for all
     * @param  int  $total  the results of all the groups
     * @param  list<SearchGroupData>  $groups  only the groups with a count above zero, in the order of the groups
     */
    public function __construct(
        public readonly string $query,
        public readonly ?SearchGroup $type,
        public readonly int $total,
        #[DataCollectionOf(SearchGroupData::class)]
        public readonly array $groups,
    ) {}
}
