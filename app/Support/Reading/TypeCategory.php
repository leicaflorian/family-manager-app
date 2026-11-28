<?php

declare(strict_types=1);

namespace App\Support\Reading;

use App\Enums\UtilityType;

/**
 * The category of a type of utility among the categories of a family: the one named as the category a new family
 * gets for that type (`DefaultCategories`). A family has no language of its own (its categories are named in the
 * language of whoever created it), so the default names of both languages count, whoever reads the paper. Works for
 * document and expense categories alike, on the paths the reading lists (`Home and utilities › Gas`).
 */
final class TypeCategory
{
    /** The separator of the names in a path of the lists, as `ReadingContext` writes it. */
    private const string SEPARATOR = ' › ';

    /** The mark between the names of a path, without the spaces. */
    private const string MARK = '›';

    /** The languages of the app, whose default names a category can have. */
    private const array LOCALES = ['en', 'it'];

    /** The default name of the category of each type, an English translation key. */
    private const array NAMES = [
        'electricity' => 'Electricity',
        'gas' => 'Gas',
        'water' => 'Water',
        'internet' => 'Internet',
        'phone' => 'Phone',
        'waste_tax' => 'Waste tax (TARI)',
        'tv_license' => 'TV licence',
    ];

    /**
     * The first category whose last name of the path is, in any case, a default name of the type.
     *
     * @param  UtilityType  $type  the type of the utility
     * @param  list<array{id: int, label: string}>  $choices  the categories, with their paths as labels
     */
    public static function of(UtilityType $type, array $choices): ?int
    {
        $wanted = array_map(
            static fn (string $locale): string => mb_strtolower(trim(__(self::NAMES[$type->value], [], $locale))),
            self::LOCALES,
        );

        foreach ($choices as $choice) {
            $names = self::names($choice['label']);
            if (in_array(mb_strtolower((string) end($names)), $wanted, true)) {
                return $choice['id'];
            }
        }

        return null;
    }

    /**
     * The category right above the categories of all the types («Casa e utenze» for «Casa e utenze › Luce» and
     * «Casa e utenze › Gas»): null when a type has no category, when one of them has no parent, when the parents are
     * different or when the parent is not in the list.
     *
     * @param  list<UtilityType>  $types  the types of the supplies
     * @param  list<array{id: int, label: string}>  $choices  the categories, with their paths as labels
     */
    public static function commonParent(array $types, array $choices): ?int
    {
        $parents = [];

        foreach ($types as $type) {
            $id = self::of($type, $choices);
            $label = $id === null ? null : collect($choices)->firstWhere('id', $id)['label'] ?? null;
            $names = is_string($label) ? self::names($label) : [];
            if (count($names) < 2) {
                return null;
            }

            $parents[] = implode(self::SEPARATOR, array_slice($names, 0, -1));
        }

        $parents = array_values(array_unique($parents));
        if (count($parents) !== 1) {
            return null;
        }

        $parent = collect($choices)->first(static fn (array $choice): bool => implode(self::SEPARATOR, self::names($choice['label'])) === $parents[0]);

        return $parent['id'] ?? null;
    }

    /**
     * The names of a path, without the spaces around them.
     *
     * @return list<string>
     */
    private static function names(string $path): array
    {
        return array_map(trim(...), explode(self::MARK, $path));
    }
}
