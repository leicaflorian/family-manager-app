<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A list joined in a sentence, in the language asked: «Luce e Gas», «luce, gas e acqua». The titles of a bill of more
 * types and of the notice of a bank payment that pays more due dates are written with it.
 */
final class ListJoin
{
    /**
     * The items with commas between them and «and» before the last; one item stays as it is, none is empty.
     *
     * @param  list<string>  $items  the items, in order
     * @param  string|null  $locale  the language of «and»; null for the one of the app
     */
    public static function of(array $items, ?string $locale = null): string
    {
        $last = array_pop($items);

        return $items === [] ? (string) $last : __(':first and :second', ['first' => implode(', ', $items), 'second' => $last], $locale);
    }
}
