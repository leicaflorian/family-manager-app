<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExpenseCategory;
use Illuminate\Support\Collection;

/**
 * The path of every expense category of the current family, from its main category: «Home › Gas».
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
        $byParent = ExpenseCategory::query()->orderBy('sort')->orderBy('name')->get()
            ->groupBy(static fn (ExpenseCategory $category): int => $category->parent_id ?? 0);
        $paths = [];

        // Depth first, so a subcategory comes right after its main category; a loop in the data is never entered
        $walk = static function (int $parent, string $path) use (&$walk, &$paths, $byParent): void {
            foreach ($byParent->get($parent, new Collection) as $category) {
                if (isset($paths[$category->id])) {
                    continue;
                }

                $paths[$category->id] = $path === '' ? $category->name : "{$path} › {$category->name}";
                $walk($category->id, $paths[$category->id]);
            }
        };
        $walk(0, '');

        return $paths;
    }
}
