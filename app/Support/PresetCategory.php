<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\DocumentCategory;

/**
 * The document category an upload was opened with (`?category=<id>`), for the import form to start with: read from
 * the query only when it is a category of the family, so nothing of another family is used or echoed.
 */
final class PresetCategory
{
    /**
     * The category of the query, if it is one of the family.
     *
     * @param  mixed  $category  the `category` of the query, a document category id
     * @return int|null null when it is malformed or missing from the family
     */
    public static function from(mixed $category): ?int
    {
        if (! is_string($category) || preg_match('/^\d+$/', $category) !== 1) {
            return null;
        }

        return DocumentCategory::query()->whereKey((int) $category)->exists() ? (int) $category : null;
    }

    /**
     * The query to put back on the next address of the upload: the category when it is valid, nothing otherwise.
     *
     * @param  mixed  $category  the `category` of the query
     * @return array{category?: int}
     */
    public static function query(mixed $category): array
    {
        $preset = self::from($category);

        return $preset === null ? [] : ['category' => $preset];
    }
}
