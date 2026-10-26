<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Il tipo di un'iscrizione (`enrollments.type`).
 * I valori sono quelli di Directus.
 */
enum TipoIscrizione: string
{
    use HaEtichetta;

    case School = 'school';
    case Sport = 'sport';
    case Course = 'course';
    case Other = 'other';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::School => 'Scuola',
            self::Sport => 'Sport',
            self::Course => 'Corso',
            self::Other => 'Altro',
        };
    }
}
