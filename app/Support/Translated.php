<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A translated text that is always a string: `__()` may give an array or null, for a key that names a group of lines.
 */
final class Translated
{
    /**
     * The text for an English key, in the language of the app.
     *
     * @param  string  $key  the English text
     * @param  array<string, string|int>  $replace  the values of the placeholders
     * @param  string|null  $locale  the language to write in, the current one when null
     * @return string the translation, or the key itself when it has none
     */
    public static function text(string $key, array $replace = [], ?string $locale = null): string
    {
        $text = __($key, $replace, $locale);

        return is_string($text) ? $text : $key;
    }
}
