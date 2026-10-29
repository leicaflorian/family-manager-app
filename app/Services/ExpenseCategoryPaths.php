<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExpenseCategory;
use Illuminate\Support\Collection;

/**
 * The path of every expense category of the current family, from its main category: «Home › Gas», and the main
 * category each one belongs to. The tree is walked in one place, for the menus and for *Planned vs spent*.
 */
final class ExpenseCategoryPaths
{
    /**
     * The paths by id of the category, in tree order: a main category, then its subcategories.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_map(
            static fn (array $node): string => $node['path'],
            self::walk(ExpenseCategory::query()->orderBy('sort')->orderBy('name')->get()),
        );
    }

    /**
     * The path and the main category of each category, in the order given: a main category, then its subcategories.
     * A category whose parents are missing or loop is not reached from a main category, so it is left out.
     *
     * @param  Collection<int, ExpenseCategory>  $categories  All the categories, in the order to walk them.
     * @return array<int, array{path: string, main: int}> By id of the category.
     */
    public static function walk(Collection $categories): array
    {
        $byParent = $categories->groupBy(static fn (ExpenseCategory $category): int => $category->parent_id ?? 0);
        $tree = [];

        // Depth first, so a subcategory comes right after its main category; a loop in the data is never entered
        $visit = static function (int $parent, string $path, int $main) use (&$visit, &$tree, $byParent): void {
            foreach ($byParent->get($parent, new Collection) as $category) {
                if (isset($tree[$category->id])) {
                    continue;
                }

                $own = $path === '' ? $category->name : "{$path} › {$category->name}";
                $tree[$category->id] = ['path' => $own, 'main' => $main === 0 ? $category->id : $main];
                $visit($category->id, $own, $tree[$category->id]['main']);
            }
        };
        $visit(0, '', 0);

        return $tree;
    }
}
