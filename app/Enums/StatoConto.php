<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Lo stato di un conto (`financial_accounts.status`).
 * I valori sono quelli di Directus.
 */
enum StatoConto: string
{
    use HaEtichetta;

    case Active = 'active';
    case Closed = 'closed';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Active => 'Attivo',
            self::Closed => 'Chiuso',
        };
    }
}
