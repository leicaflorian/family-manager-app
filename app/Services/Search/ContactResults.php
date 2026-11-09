<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Data\Search\SearchResultData;
use App\Enums\SearchGroup;
use App\Models\Organization;
use App\Models\Person;
use App\Support\SearchTerms;
use Illuminate\Database\Eloquent\Builder;

/**
 * The search over the organizations and the people of the family, together, by name. The first and the last name of
 * a person are two columns, so «bianchi laura» finds *Laura Bianchi*.
 */
final readonly class ContactResults implements GroupResults
{
    /**
     * {@inheritDoc}
     */
    public function group(): SearchGroup
    {
        return SearchGroup::Contacts;
    }

    /**
     * {@inheritDoc}
     *
     * The sum of the two counts: an organization and a person are never the same row.
     */
    public function count(SearchTerms $terms): int
    {
        return $this->organizations($terms)->count() + $this->people($terms)->count();
    }

    /**
     * {@inheritDoc}
     *
     * Each kind is read with the limit, then the two lists are merged by name (case-insensitive) and cut.
     */
    public function results(SearchTerms $terms, int $limit): array
    {
        $limit = max(0, $limit);

        $organizations = $this->organizations($terms)->orderBy('name')->orderBy('id')->limit($limit)->get();
        $people = $this->people($terms)->orderBy('first_name')->orderBy('last_name')->orderBy('id')->limit($limit)->get();

        $rows = [
            ...$organizations->map(fn (Organization $organization): SearchResultData => $this->organization($organization))->all(),
            ...$people->map(fn (Person $person): SearchResultData => $this->person($person))->all(),
        ];

        // A stable sort: two contacts with the same name stay as they were read.
        usort($rows, static fn (SearchResultData $a, SearchResultData $b): int => strcmp(mb_strtolower($a->title), mb_strtolower($b->title)));

        return array_slice($rows, 0, $limit);
    }

    /**
     * The result row of an organization, with its types as subtitle.
     *
     * @param  Organization  $organization  the organization
     */
    private function organization(Organization $organization): SearchResultData
    {
        $labels = $organization->typeLabels();

        return new SearchResultData(
            key: 'organization-'.$organization->id,
            group: SearchGroup::Contacts,
            title: $organization->name,
            subtitle: $labels === [] ? null : implode(', ', $labels),
            date: null,
            amount: null,
            href: route('organizations.show', $organization->id),
            area: null,
            icon: 'building-2',
            tone: 'action',
            flag: null,
            due_in: null,
        );
    }

    /**
     * The result row of a person, titled with the full name.
     *
     * @param  Person  $person  the person
     */
    private function person(Person $person): SearchResultData
    {
        return new SearchResultData(
            key: 'person-'.$person->id,
            group: SearchGroup::Contacts,
            title: trim("{$person->first_name} {$person->last_name}"),
            subtitle: null,
            date: null,
            amount: null,
            href: route('people.show', $person->id),
            area: null,
            icon: 'user',
            tone: 'action',
            flag: null,
            due_in: null,
        );
    }

    /**
     * The organizations where every word is in the name.
     *
     * @param  SearchTerms  $terms  what the person typed
     * @return Builder<Organization>
     */
    private function organizations(SearchTerms $terms): Builder
    {
        return $terms->whereWords(Organization::with([]), ['name']);
    }

    /**
     * The people where every word is in the first or the last name.
     *
     * @param  SearchTerms  $terms  what the person typed
     * @return Builder<Person>
     */
    private function people(SearchTerms $terms): Builder
    {
        return $terms->whereWords(Person::with([]), ['first_name', 'last_name']);
    }
}
