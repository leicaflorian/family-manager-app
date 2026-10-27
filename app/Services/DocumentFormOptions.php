<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CategoryData;
use App\Data\LinkGroupData;
use App\Data\OptionData;
use App\Enums\DocumentStatus;
use App\Models\Organization;
use App\Models\Person;

/**
 * The choices of the document form, the same in *Upload* and in *Edit a
 * document*: categories, people, organizations, links and statuses. The
 * filters of *Documents* use the same, without the links.
 */
final readonly class DocumentFormOptions
{
    public function __construct(private CategoryTree $categories, private DocumentLinks $links) {}

    /**
     * The props of the form, ready for the page.
     *
     * @return array{categories: list<CategoryData>, people: list<OptionData>, organizations: list<OptionData>, links: list<LinkGroupData>, statuses: list<OptionData>}
     */
    public function all(): array
    {
        return [...$this->filters(), 'links' => $this->links->groups()];
    }

    /**
     * The choices of the filters of *Documents*.
     *
     * @return array{categories: list<CategoryData>, people: list<OptionData>, organizations: list<OptionData>, statuses: list<OptionData>}
     */
    public function filters(): array
    {
        return [
            'categories' => $this->categories->tree(),
            'people' => array_values(Person::query()->orderBy('first_name')->orderBy('last_name')->get()
                ->map(static fn (Person $person): OptionData => new OptionData((string) $person->id, "{$person->first_name} {$person->last_name}"))
                ->all()),
            'organizations' => array_values(Organization::query()->orderBy('name')->get()
                ->map(static fn (Organization $organization): OptionData => new OptionData((string) $organization->id, $organization->name))
                ->all()),
            'statuses' => OptionData::list(DocumentStatus::options()),
        ];
    }
}
