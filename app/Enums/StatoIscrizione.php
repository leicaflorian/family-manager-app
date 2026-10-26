<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Lo stato di un'iscrizione (`enrollments.status`).
 * I valori sono quelli di Directus.
 */
enum StatoIscrizione: string
{
    use HaEtichetta;

    case Active = 'active';
    case Ended = 'ended';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Active => 'Attiva',
            self::Ended => 'Conclusa',
        };
    }
}
