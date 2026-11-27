<?php

declare(strict_types=1);

namespace App\Support\Reading;

use App\Enums\UtilityType;

/**
 * The category of a type of utility among the categories of a family: the one named as the category a new family
 * gets for that type (`DefaultCategories`), in the language of the family. Works for document and expense
 * categories alike, on the paths the reading lists (`Home and utilities › Gas`).
 */
final class TypeCategory
{
    /** The separator of the names in a path of the lists. */
    private const string SEPARATOR = '›';

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
     * The first category whose last name of the path is, in any case, the default name of the type.
     *
     * @param  UtilityType  $type  the type of the utility
     * @param  list<array{id: int, label: string}>  $choices  the categories, with their paths as labels
     * @param  string  $locale  the language the family's categories are named in
     */
    public static function of(UtilityType $type, array $choices, string $locale): ?int
    {
        $wanted = mb_strtolower(__(self::NAMES[$type->value], [], $locale));

        foreach ($choices as $choice) {
            if (mb_strtolower(self::last($choice['label'])) === $wanted) {
                return $choice['id'];
            }
        }

        return null;
    }

    /**
     * The main category the categories of all the types are under: null when a type has no category, when they
     * are under different main categories or when the main category is not in the list.
     *
     * @param  list<UtilityType>  $types  the types of the supplies
     * @param  list<array{id: int, label: string}>  $choices  the categories, with their paths as labels
     * @param  string  $locale  the language the family's categories are named in
     */
    public static function commonMain(array $types, array $choices, string $locale): ?int
    {
        $mains = [];

        foreach ($types as $type) {
            $id = self::of($type, $choices, $locale);
            if ($id === null) {
                return null;
            }

            $label = collect($choices)->firstWhere('id', $id)['label'] ?? '';
            $mains[] = trim(explode(self::SEPARATOR, (string) $label)[0]);
        }

        $mains = array_unique($mains);
        if (count($mains) !== 1) {
            return null;
        }

        $main = collect($choices)->first(static fn (array $choice): bool => trim($choice['label']) === $mains[0]);

        return $main['id'] ?? null;
    }

    /**
     * The last name of a path.
     */
    private static function last(string $path): string
    {
        $names = explode(self::SEPARATOR, $path);

        return trim(end($names));
    }
}
