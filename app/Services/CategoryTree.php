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

        return $this->branches($categories->toBase()->groupBy('parent_id'), null, null);
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
     * The categories below `$parent`, each with its children.
     *
     * @param  Collection<int|string, Collection<int, DocumentCategory>>  $byParent  the categories grouped by `parent_id`
     * @param  int|null  $parent  the category whose children to read, null for the main ones
     * @param  string|null  $color  the colour of the main category, for the subcategories
     * @return list<CategoryData>
     */
    private function branches(Collection $byParent, ?int $parent, ?string $color): array
    {
        return array_values($byParent->get($parent ?? '', collect())->map(fn (DocumentCategory $category): CategoryData => new CategoryData(
            id: $category->id,
            name: $category->name,
            parent_id: $category->parent_id,
            icon: $category->icon,
            color: $category->color ?? $color,
            documents_count: (int) $category->documents_count,
            children: $this->branches($byParent, $category->id, $category->color ?? $color),
        ))->all());
    }
}
