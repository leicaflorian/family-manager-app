<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Ogni quanto si ripete una spesa o entrata prevista.
 * I valori sono quelli di Directus.
 */
enum UnitaIntervallo: string
{
    use HaEtichetta;

    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Week => 'Settimana',
            self::Month => 'Mese',
            self::Year => 'Anno',
        };
    }
}
