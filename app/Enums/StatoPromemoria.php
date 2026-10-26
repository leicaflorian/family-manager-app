<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Lo stato di un promemoria (`reminders.status`).
 * I valori sono quelli di Directus.
 */
enum StatoPromemoria: string
{
    use HaEtichetta;

    case Open = 'open';
    case Done = 'done';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Open => 'Aperto',
            self::Done => 'Fatto',
        };
    }
}
