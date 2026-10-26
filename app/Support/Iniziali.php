<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Le iniziali di una persona, le due lettere del cerchio.
 */
final class Iniziali
{
    /**
     * Le iniziali di nome e cognome, in maiuscolo.
     *
     * @param  string  $nome  il nome
     * @param  string  $cognome  il cognome
     * @return string per esempio `MB`
     */
    public static function di(string $nome, string $cognome): string
    {
        return Str::upper(Str::substr($nome, 0, 1).Str::substr($cognome, 0, 1));
    }
}
