<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A path of the app to go back to, read from the address: only a path on the same site (it starts with one `/`,
 * not `//` or `/\`, which a browser reads as another site), so a link cannot send anyone elsewhere.
 */
final class LocalPath
{
    /**
     * The path, or null when it is not one of the app.
     *
     * @param  mixed  $value  what the address carries, for example `?back=/agenda`
     */
    public static function of(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('#^/(?![/\\\\])[^\x00-\x1F\x7F]*$#', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
