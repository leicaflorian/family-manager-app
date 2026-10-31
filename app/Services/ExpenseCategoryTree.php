<?php

declare(strict_types=1);

namespace App\Services;

use App\Cards\CardTypes;
use App\Data\ExpenseCategoryData;
use App\Models\ExpenseCategory;
use Illuminate\Support\Collection;

/**
 * The tree of the expense categories of the current family, with the card type of each one.
 *
 * The categories are read with one query and the tree is built in memory.
 */
final class ExpenseCategoryTree
{
    /**
     * The tree, from the main categories.
     *
     * @return list<ExpenseCategoryData>
     */
    public function tree(): array
    {
        $categories = ExpenseCategory::query()->orderBy('sort')->orderBy('name')->get();

        return $this->branches($categories->toBase()->groupBy(static fn (ExpenseCategory $category): int => $category->parent_id ?? 0), 0, null, null);
    }

    /**
     * The categories below `$parent`, each with its children.
     *
     * @param  Collection<int, Collection<int, ExpenseCategory>>  $byParent  the categories grouped by `parent_id`, 0 for the main ones
     * @param  int  $parent  the category whose children to read, 0 for the main ones
     * @param  string|null  $color  the colour of the main category, for the subcategories
     * @param  string|null  $cardType  the card type of the main category, for the subcategories
     * @return list<ExpenseCategoryData>
     */
    private function branches(Collection $byParent, int $parent, ?string $color, ?string $cardType): array
    {
        return array_values($byParent->get($parent, collect())->map(function (ExpenseCategory $category) use ($byParent, $parent, $color, $cardType): ExpenseCategoryData {
            // Only a main category has a card type of its own: a subcategory takes the one of its main category.
            $type = $parent === 0 ? $category->card_type : $cardType;
            $ownColor = $category->color ?? $color;

            return new ExpenseCategoryData(
                id: $category->id,
                name: $category->name,
                parent_id: $category->parent_id,
                icon: $category->icon,
                color: $ownColor,
                card_type: $type,
                card_label: $type === null ? null : CardTypes::find($type)?->label(),
                children: $this->branches($byParent, $category->id, $ownColor, $type),
            );
        })->all());
    }
}
