<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Search\SearchGroupData;
use App\Data\Search\SearchResultsData;
use App\Enums\Feature;
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
        private FamilyFeatures $features,
    ) {
        $this->groups = [$cards, $documents, $movements, $dues, $contacts];
    }

    /**
     * The groups the family can search: *Movements* and *Dues* belong to the Budget.
     *
     * @return list<GroupResults>
     */
    private function available(): array
    {
        $budget = $this->features->on(Feature::Budget);

        return array_values(array_filter(
            $this->groups,
            static fn (GroupResults $results): bool => $budget || ! in_array($results->group(), [SearchGroup::Movements, SearchGroup::Dues], true),
        ));
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
        $groups = $this->available();

        foreach ($groups as $i => $results) {
            $counts[$i] = $results->count($terms);
        }

        // A group that found nothing cannot be narrowed to: the page lists all the groups instead of an empty one
        if ($only instanceof SearchGroup && ! $this->found($only, $groups, $counts)) {
            $only = null;
        }

        $found = [];
        $total = 0;

        foreach ($groups as $i => $results) {
            $count = $counts[$i];

            if ($count === 0) {
                continue;
            }

            $total += $count;
            $group = $results->group();
            $found[] = new SearchGroupData(
                group: $group,
                label: $group->label(),
                count: $count,
                results: ! $only instanceof SearchGroup || $only === $group ? $results->results($terms, $limit) : [],
            );
        }

        return new SearchResultsData($terms->query, $only, $total, $found);
    }

    /**
     * Whether a group found something.
     *
     * @param  SearchGroup  $group  the group to look at
     * @param  list<GroupResults>  $groups  the groups searched
     * @param  array<int, int>  $counts  the matches of each group, by position
     */
    private function found(SearchGroup $group, array $groups, array $counts): bool
    {
        foreach ($groups as $i => $results) {
            if ($results->group() === $group) {
                return $counts[$i] > 0;
            }
        }

        return false;
    }
}
