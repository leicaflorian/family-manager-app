<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Lo stato di un bene (`belongings.status`).
 * I valori sono quelli di Directus.
 */
enum StatoBene: string
{
    use HaEtichetta;

    case InUse = 'in_use';
    case Disposed = 'disposed';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::InUse => 'In uso',
            self::Disposed => 'Dismesso',
        };
    }
}
