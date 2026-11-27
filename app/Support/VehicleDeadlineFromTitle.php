<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Which deadline of a vehicle a planned expense pays, read from its title: «bollo» or «road tax» is the road tax,
 * «revision(e)» or «inspection» the inspection. Used where planned expenses come from outside the form (the data
 * migration and the copy from Directus).
 */
final class VehicleDeadlineFromTitle
{
    /** The deadline of each word, in lower case, in the order they are tried. */
    public const array WORDS = [
        'vehicles.road_tax_due_date' => ['bollo', 'road tax'],
        'vehicles.inspection_due_date' => ['revision', 'inspection'],
    ];

    /**
     * The deadline a title says.
     *
     * @param  string|null  $title  the title of the planned expense
     * @return string|null the key of the deadline, or null when the title says none
     */
    public static function of(?string $title): ?string
    {
        $title = mb_strtolower((string) $title);

        foreach (self::WORDS as $field => $words) {
            foreach ($words as $word) {
                if (str_contains($title, $word)) {
                    return $field;
                }
            }
        }

        return null;
    }
}
