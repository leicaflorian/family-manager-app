<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Lo stato di un abbonamento (`subscriptions.status`).
 * I valori sono quelli di Directus.
 */
enum StatoAbbonamento: string
{
    use HaEtichetta;

    case Active = 'active';
    case Cancelled = 'cancelled';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Active => 'Attivo',
            self::Cancelled => 'Disdetto',
        };
    }
}
