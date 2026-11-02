<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The article before a date written as numbers, in Italian: «il 21/11/2026» but «l'1/12», «l'8/12» and «l'11/12/2026».
 * The translations cannot do it, because the day changes; a text with «il :date» goes through here once it is composed.
 */
final class ItalianArticle
{
    /**
     * Writes «l'» instead of «il» before the days 1, 8 and 11 (with or without the zero in front). Only in Italian.
     *
     * @param  string  $text  a composed text, which may have «il» before dates as `dd/mm/yyyy`
     * @return string the same text, with the article elided where it has to be
     */
    public static function elide(string $text): string
    {
        if (app()->getLocale() !== 'it') {
            return $text;
        }

        return (string) preg_replace_callback(
            '#\b([Ii])l (0?1|0?8|11)(?=/)#',
            static fn (array $match): string => ($match[1] === 'I' ? "L'" : "l'").$match[2],
            $text,
        );
    }
}
