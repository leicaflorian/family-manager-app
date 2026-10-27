<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A person's initials, the two letters in their circle.
 */
final class Initials
{
    /**
     * The initials of first and last name, upper case.
     *
     * @param  string  $firstName  the first name
     * @param  string  $lastName  the last name
     * @return string for example `MB`
     */
    public static function of(string $firstName, string $lastName): string
    {
        return Str::upper(Str::substr($firstName, 0, 1).Str::substr($lastName, 0, 1));
    }
}
