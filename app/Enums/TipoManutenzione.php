<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Il tipo di una manutenzione (`maintenance_records.type`).
 * I valori sono quelli di Directus.
 */
enum TipoManutenzione: string
{
    use HaEtichetta;

    case Service = 'service';
    case Inspection = 'inspection';
    case Repair = 'repair';
    case BoilerService = 'boiler_service';
    case Other = 'other';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Service => 'Tagliando',
            self::Inspection => 'Revisione',
            self::Repair => 'Riparazione',
            self::BoilerService => 'Manutenzione caldaia',
            self::Other => 'Altro',
        };
    }
}
