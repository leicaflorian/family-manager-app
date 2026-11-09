<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Search\SearchGroupData;
use App\Data\Search\SearchResultsData;
use App\Enums\SearchGroup;
use App\Services\Search\CardResults;
use App\Services\Search\ContactResults;
use App\Services\Search\DocumentResults;
use App\Services\Search\DueResults;
use App\Services\Search\GroupResults;
use App\Services\Search\MovementResults;
use App\Support\SearchTerms;

/**
 * The global search: asks each group of results what it found and puts the answers together, in the order of the groups.
 */
final readonly class GlobalSearch
{
    /** @var list<GroupResults> the five groups, in the order they are shown */
    private array $groups;

    public function __construct(
        CardResults $cards,
        DocumentResults $documents,
        MovementResults $movements,
        DueResults $dues,
        ContactResults $contacts,
    ) {
        $this->groups = [$cards, $documents, $movements, $dues, $contacts];
    }

    /**
     * Searches the family.
     *
     * @param  SearchTerms  $terms  what the person typed
     * @param  SearchGroup|null  $only  the one group whose results are listed; every group is still counted
     * @param  int  $limit  the most results listed per group
     */
    public function search(SearchTerms $terms, ?SearchGroup $only, int $limit): SearchResultsData
    {
        if (! $terms->isSearchable()) {
            return new SearchResultsData($terms->query, $only, 0, []);
        }

        $groups = [];
        $total = 0;

        foreach ($this->groups as $results) {
            $count = $results->count($terms);

            if ($count === 0) {
                continue;
            }

            $total += $count;
            $group = $results->group();
            $groups[] = new SearchGroupData(
                group: $group,
                label: $group->label(),
                count: $count,
                results: ! $only instanceof SearchGroup || $only === $group ? $results->results($terms, $limit) : [],
            );
        }

        return new SearchResultsData($terms->query, $only, $total, $groups);
    }
}
