<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Lo stato di un veicolo (`vehicles.status`).
 * I valori sono quelli di Directus.
 */
enum StatoVeicolo: string
{
    use HaEtichetta;

    case Active = 'active';
    case Sold = 'sold';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Active => 'Attivo',
            self::Sold => 'Venduto',
        };
    }
}
