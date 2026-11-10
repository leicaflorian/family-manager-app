<?php

declare(strict_types=1);

use App\Data\Search\SearchResultData;
use App\Enums\OrganizationType;
use App\Enums\SearchGroup;
use App\Models\Family;
use App\Models\Organization;
use App\Models\Person;
use App\Services\Search\ContactResults;
use App\Support\CurrentFamily;
use App\Support\SearchTerms;

beforeEach(function (): void {
    $this->family = Family::factory()->create();
});

/**
 * Makes an organization in a family.
 *
 * @param  list<OrganizationType>  $types  its types
 */
function searchOrganization(Family $family, string $name, array $types = []): Organization
{
    return CurrentFamily::using($family->id, fn (): Organization => Organization::factory()->create(['family_id' => $family->id, 'name' => $name, 'types' => $types]));
}

/** Makes a person in a family. */
function searchPerson(Family $family, string $first, string $last): Person
{
    return CurrentFamily::using($family->id, fn (): Person => Person::factory()->create(['family_id' => $family->id, 'first_name' => $first, 'last_name' => $last]));
}

/**
 * The results found for some text, inside the family.
 *
 * @return list<SearchResultData>
 */
function contactResults(Family $family, string $text, int $limit = 10): array
{
    return CurrentFamily::using($family->id, fn (): array => resolve(ContactResults::class)->results(SearchTerms::fromQuery($text), $limit));
}

it('belongs to the contacts group', function (): void {
    expect(resolve(ContactResults::class)->group())->toBe(SearchGroup::Contacts);
});

it('finds an organization by name with its types as subtitle', function (): void {
    $enel = searchOrganization($this->family, 'Enel Energia', [OrganizationType::UtilityProvider]);
    searchOrganization($this->family, 'Comune');

    $results = contactResults($this->family, 'enel');

    expect($results)->toHaveCount(1)
        ->and($results[0]->key)->toBe('organization-'.$enel->id)
        ->and($results[0]->group)->toBe(SearchGroup::Contacts)
        ->and($results[0]->title)->toBe('Enel Energia')
        ->and($results[0]->subtitle)->toBe(OrganizationType::UtilityProvider->label())
        ->and($results[0]->href)->toBe(route('organizations.show', $enel->id))
        ->and($results[0]->icon)->toBe('building-2')
        ->and($results[0]->tone)->toBe('action')
        ->and($results[0]->area)->toBeNull();
});

it('finds a person by name, also with two words on two columns', function (): void {
    $laura = searchPerson($this->family, 'Laura', 'Bianchi');

    foreach (['laura', 'bianchi laura'] as $text) {
        $results = contactResults($this->family, $text);

        expect($results)->toHaveCount(1)
            ->and($results[0]->key)->toBe('person-'.$laura->id)
            ->and($results[0]->title)->toBe('Laura Bianchi')
            ->and($results[0]->subtitle)->toBeNull()
            ->and($results[0]->href)->toBe(route('people.show', $laura->id))
            ->and($results[0]->icon)->toBe('user')
            ->and($results[0]->tone)->toBe('action');
    }
});

it('sorts organizations and people together by name and counts both', function (): void {
    searchOrganization($this->family, 'Zeta Banca');
    searchPerson($this->family, 'Anna', 'Zeta');
    searchOrganization($this->family, 'bar Zeta');

    $terms = SearchTerms::fromQuery('zeta');

    expect(array_map(static fn (SearchResultData $r): string => $r->title, contactResults($this->family, 'zeta')))->toBe(['Anna Zeta', 'bar Zeta', 'Zeta Banca'])
        ->and(array_map(static fn (SearchResultData $r): string => $r->title, contactResults($this->family, 'zeta', 2)))->toBe(['Anna Zeta', 'bar Zeta'])
        ->and(CurrentFamily::using($this->family->id, fn (): int => resolve(ContactResults::class)->count($terms)))->toBe(3);
});

it('does not find the contacts of another family', function (): void {
    $other = Family::factory()->create();
    searchOrganization($other, 'Enel Energia');
    searchPerson($other, 'Laura', 'Bianchi');

    expect(contactResults($this->family, 'enel'))->toBe([])
        ->and(contactResults($this->family, 'laura'))->toBe([]);
});
