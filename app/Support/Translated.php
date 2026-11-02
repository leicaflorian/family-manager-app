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
     * @return string the translation, or the key itself when it has none
     */
    public static function text(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }
}
