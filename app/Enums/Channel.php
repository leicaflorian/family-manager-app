<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Where a notice of a reminder group arrives: the bell in the app, the email, or both.
 */
enum Channel: string
{
    use HasLabel;

    case App = 'app';
    case Email = 'email';
    case Both = 'both';

    /**
     * The channel of a saved value; an unknown one reads as both, like Directus.
     *
     * @param  mixed  $value  the saved value
     */
    public static function read(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::Both) : self::Both;
    }
}
