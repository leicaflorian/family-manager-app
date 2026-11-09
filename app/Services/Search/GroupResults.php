<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Data\Search\SearchResultData;
use App\Enums\SearchGroup;
use App\Support\SearchTerms;

/**
 * The search of one group of the global search: how many rows match and the first ones to show.
 */
interface GroupResults
{
    /**
     * The group these results belong to.
     */
    public function group(): SearchGroup;

    /**
     * How many rows of the group match, without a limit.
     *
     * @param  SearchTerms  $terms  what the person typed
     */
    public function count(SearchTerms $terms): int;

    /**
     * The first rows of the group that match, in the order they are shown.
     *
     * @param  SearchTerms  $terms  what the person typed
     * @param  int  $limit  the most rows to return
     * @return list<SearchResultData>
     */
    public function results(SearchTerms $terms, int $limit): array;
}
