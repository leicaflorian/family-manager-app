<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The title a document gets from the name of its file, before anyone writes
 * one: `Busta_paga-novembre 2026.pdf` becomes «Busta paga novembre 2026».
 */
final class FileTitle
{
    /**
     * The name without its last extension, with dashes and underscores turned
     * into spaces. A name left empty keeps the whole original name.
     *
     * @param  string  $name  the original name of the file
     */
    public static function from(string $name): string
    {
        $title = Str::squish(str_replace(['_', '-'], ' ', pathinfo($name, PATHINFO_FILENAME)));

        return $title === '' ? $name : $title;
    }
}
