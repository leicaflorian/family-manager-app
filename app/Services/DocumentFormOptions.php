<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CategoryData;
use App\Data\LinkGroupData;
use App\Data\OptionData;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Person;

/**
 * The choices of the document form, the same in *Upload* and in *Edit a
 * document*: categories, people, organizations, links, statuses and the tags
 * already used. The filters of *Documents* use the same, without the links and
 * the tags.
 */
final readonly class DocumentFormOptions
{
    public function __construct(private CategoryTree $categories, private DocumentLinks $links) {}

    /**
     * The props of the form, ready for the page.
     *
     * @return array{categories: list<CategoryData>, people: list<OptionData>, organizations: list<OptionData>, links: list<LinkGroupData>, statuses: list<OptionData>, tags: list<string>}
     */
    public function all(): array
    {
        return [...$this->filters(), 'links' => $this->links->groups(), 'tags' => $this->tags()];
    }

    /**
     * The tags of the documents of the family, once each and in alphabetical
     * order (upper and lower case together), so that a document can reuse them.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return array_values(Document::query()->whereNotNull('tags')->pluck('tags')
            ->flatten()
            ->filter(static fn (mixed $tag): bool => is_string($tag))
            ->unique()
            ->sort(SORT_STRING | SORT_FLAG_CASE)
            ->all());
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
