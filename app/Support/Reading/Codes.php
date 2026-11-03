<?php

declare(strict_types=1);

namespace App\Support\Reading;

/**
 * Codes printed on papers (POD, plate, policy number, tax code), compared
 * without the spaces, dots and dashes they are written with.
 */
final class Codes
{
    /**
     * The code as uppercase letters and digits only.
     *
     * @param  string|null  $code  the code as printed or stored
     * @return string the normalised code, empty for null or nothing left
     */
    public static function normalize(?string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($code ?? '')) ?? '';
    }
}
