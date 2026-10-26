<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Lo stato di una casa (`homes.status`).
 * I valori sono quelli di Directus.
 */
enum StatoCasa: string
{
    use HaEtichetta;

    case Active = 'active';
    case Archived = 'archived';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Active => 'Attiva',
            self::Archived => 'Archiviata',
        };
    }
}
