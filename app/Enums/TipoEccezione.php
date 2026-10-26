<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Che cosa cambia un'eccezione di una spesa o entrata prevista.
 * I valori sono quelli di Directus.
 */
enum TipoEccezione: string
{
    use HaEtichetta;

    case Skip = 'skip';
    case AmountOverride = 'amount_override';
    case DateOverride = 'date_override';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Skip => 'Saltata',
            self::AmountOverride => 'Importo diverso',
            self::DateOverride => 'Data diversa',
        };
    }
}
