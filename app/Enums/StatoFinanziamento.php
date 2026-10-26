<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Lo stato di un finanziamento (`loans.status`).
 * I valori sono quelli di Directus.
 */
enum StatoFinanziamento: string
{
    use HaEtichetta;

    case Active = 'active';
    case PaidOff = 'paid_off';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Active => 'Attivo',
            self::PaidOff => 'Estinto',
        };
    }
}
