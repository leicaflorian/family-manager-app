<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CategoryData;
use App\Models\DocumentCategory;
use Illuminate\Support\Collection;

/**
 * The tree of the document categories of the current family.
 *
 * A family has a few dozen categories at most: they are all read with one
 * query and the tree is built in memory.
 */
final class CategoryTree
{
    /**
     * The tree, from the main categories, with the number of documents of each.
     *
     * @return list<CategoryData>
     */
    public function tree(): array
    {
        $categories = DocumentCategory::query()->withCount('documents')->orderBy('sort')->orderBy('name')->get();

        return $this->branches($categories->toBase()->groupBy('parent_id'), null, null, null);
    }

    /**
     * The path of every category, from its main category: «Work › Payslips»
     * is `['Work', 'Payslips']`.
     *
     * @return array<int, list<string>> the names, by id of the category
     */
    public function paths(): array
    {
        $categories = DocumentCategory::query()->get(['id', 'parent_id', 'name'])->keyBy('id');
        $paths = [];

        foreach ($categories as $category) {
            $names = [];

            // Up to the main category; the visited ids stop a loop, should the data have one.
            for ($current = $category, $seen = []; $current !== null && ! isset($seen[$current->id]); $current = $categories->get($current->parent_id ?? 0)) {
                $seen[$current->id] = true;
                array_unshift($names, $current->name);
            }

            $paths[$category->id] = $names;
        }

        return $paths;
    }

    /**
     * The card type of every category: its own, else the one of the nearest category above with one.
     *
     * @return array<int, string> the type of the morph map by id of the category; categories with none up the tree are missing
     */
    public function cardTypes(): array
    {
        $categories = DocumentCategory::query()->get(['id', 'parent_id', 'card_type'])->keyBy('id');
        $types = [];

        foreach ($categories as $id => $category) {
            // The visited ids stop a loop, should the data have one.
            for ($current = $category, $seen = []; $current !== null && ! isset($seen[$current->id]); $current = $categories->get($current->parent_id ?? 0)) {
                $seen[$current->id] = true;
                if ($current->card_type !== null) {
                    $types[$id] = $current->card_type;

                    break;
                }
            }
        }

        return $types;
    }

    /**
     * The ids of a category and of all those below it, at every level.
     *
     * @param  int  $id  the category to start from
     * @return list<int>
     */
    public function withDescendants(int $id): array
    {
        $byParent = DocumentCategory::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        $found = [$id];

        // A breadth-first visit: every category found queues its children.
        for ($i = 0; $i < count($found); $i++) {
            foreach ($byParent->get($found[$i], collect()) as $child) {
                $found[] = $child->id;
            }
        }

        return $found;
    }

    /**
     * The ids of the gallery category and of all its subcategories.
     *
     * @return list<int> empty when no category of the family is the gallery
     */
    public function galleryIds(): array
    {
        $gallery = $this->galleryCategory();

        return $gallery instanceof DocumentCategory ? $this->withDescendants($gallery->id) : [];
    }

    /**
     * The category shown as the drawings gallery, if the family has one.
     */
    public function galleryCategory(): ?DocumentCategory
    {
        return DocumentCategory::query()->where('gallery', true)->first();
    }

    /**
     * The categories below `$parent`, each with its children.
     *
     * @param  Collection<int|string, Collection<int, DocumentCategory>>  $byParent  the categories grouped by `parent_id`
     * @param  int|null  $parent  the category whose children to read, null for the main ones
     * @param  string|null  $color  the colour of the main category, for the subcategories
     * @param  string|null  $cardType  the card of the nearest category above with one, for the subcategories
     * @return list<CategoryData>
     */
    private function branches(Collection $byParent, ?int $parent, ?string $color, ?string $cardType): array
    {
        return array_values($byParent->get($parent ?? '', collect())->map(fn (DocumentCategory $category): CategoryData => new CategoryData(
            id: $category->id,
            name: $category->name,
            parent_id: $category->parent_id,
            icon: $category->icon,
            color: $category->color ?? $color,
            card_type: $category->card_type,
            gallery: $category->gallery,
            inherited_card_type: $cardType,
            documents_count: (int) $category->documents_count,
            children: $this->branches($byParent, $category->id, $category->color ?? $color, $category->card_type ?? $cardType),
        ))->all());
    }
}
