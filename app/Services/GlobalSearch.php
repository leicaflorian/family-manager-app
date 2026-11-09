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
     * @param  SearchGroup|null  $only  the one group whose results are listed; every group is still counted. When it found nothing, all the groups are listed
     * @param  int  $limit  the most results listed per group
     */
    public function search(SearchTerms $terms, ?SearchGroup $only, int $limit): SearchResultsData
    {
        if (! $terms->isSearchable()) {
            return new SearchResultsData($terms->query, $only, 0, []);
        }

        /** @var array<int, int> $counts the matches of each group, by position */
        $counts = [];

        foreach ($this->groups as $i => $results) {
            $counts[$i] = $results->count($terms);
        }

        // A group that found nothing cannot be narrowed to: the page lists all the groups instead of an empty one
        if ($only instanceof SearchGroup && ! $this->found($only, $counts)) {
            $only = null;
        }

        $groups = [];
        $total = 0;

        foreach ($this->groups as $i => $results) {
            $count = $counts[$i];

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

    /**
     * Whether a group found something.
     *
     * @param  SearchGroup  $group  the group to look at
     * @param  array<int, int>  $counts  the matches of each group, by position
     */
    private function found(SearchGroup $group, array $counts): bool
    {
        foreach ($this->groups as $i => $results) {
            if ($results->group() === $group) {
                return $counts[$i] > 0;
            }
        }

        return false;
    }
}
