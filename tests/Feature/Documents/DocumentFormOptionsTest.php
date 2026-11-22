<?php

declare(strict_types=1);

use App\Data\CategoryData;
use App\Data\OrganizationOptionData;
use App\Enums\OrganizationType;
use App\Models\DocumentCategory;
use App\Models\Organization;
use App\Services\DocumentFormOptions;
use App\Support\CurrentFamily;

it('gives each organization its types and its logo, for the mark in the field Who it is from', function (): void {
    [$edison, $organizations] = insideFamilyOf(registerFamily(), function (): array {
        $edison = Organization::factory()->create(['family_id' => CurrentFamily::id(), 'name' => 'Edison', 'types' => [OrganizationType::UtilityProvider]]);

        return [$edison, resolve(DocumentFormOptions::class)->filters()['organizations']];
    });

    expect($organizations)->toHaveCount(1)
        ->and($organizations[0])->toBeInstanceOf(OrganizationOptionData::class)
        ->and($organizations[0]->toArray())->toBe([
            'value' => (string) $edison->id,
            'label' => 'Edison',
            'help' => null,
            'types' => ['utility_provider'],
            'logo' => null,
        ]);
});

/**
 * The ids of a tree of categories, the subcategories too.
 *
 * @param  list<CategoryData>  $tree  the categories
 * @return list<int>
 */
function documentFormOptionsIds(array $tree): array
{
    return array_merge([], ...array_map(fn (CategoryData $category): array => [$category->id, ...documentFormOptionsIds($category->children)], $tree));
}

it('leaves the gallery category and its subcategories out of the filters, not out of the form', function (): void {
    [$gallery, $child, $filters, $form] = insideFamilyOf(registerFamily(), function (): array {
        $gallery = DocumentCategory::query()->where('name', 'Drawings and crafts')->sole();
        $gallery->update(['gallery' => true]);
        $child = DocumentCategory::factory()->create(['family_id' => CurrentFamily::id(), 'parent_id' => $gallery->id, 'name' => 'Crafts']);
        $options = resolve(DocumentFormOptions::class);

        return [$gallery, $child, documentFormOptionsIds($options->filters()['categories']), documentFormOptionsIds($options->all()['categories'])];
    });

    expect($filters)->not->toContain($gallery->id)
        ->and($filters)->not->toContain($child->id)
        ->and($filters)->not->toBeEmpty()
        ->and($form)->toContain($gallery->id)
        ->and($form)->toContain($child->id);
});
