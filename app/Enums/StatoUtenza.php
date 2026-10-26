<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Lo stato di un'utenza (`utilities.status`).
 * I valori sono quelli di Directus.
 */
enum StatoUtenza: string
{
    use HaEtichetta;

    case Active = 'active';
    case Terminated = 'terminated';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Active => 'Attiva',
            self::Terminated => 'Cessata',
        };
    }
}
