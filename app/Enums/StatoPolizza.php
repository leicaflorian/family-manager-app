<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Lo stato di una polizza (`insurance_policies.status`).
 * I valori sono quelli di Directus.
 */
enum StatoPolizza: string
{
    use HaEtichetta;

    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Active => 'Attiva',
            self::Expired => 'Scaduta',
            self::Cancelled => 'Disdetta',
        };
    }
}
