<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Lo stato di un documento (`documents.status`).
 * I valori sono quelli di Directus.
 */
enum StatoDocumento: string
{
    use HaEtichetta;

    case Active = 'active';
    case Archived = 'archived';
    case Expired = 'expired';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Active => 'Attivo',
            self::Archived => 'Archiviato',
            self::Expired => 'Scaduto',
        };
    }
}
