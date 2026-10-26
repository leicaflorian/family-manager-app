<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Il tipo di un abbonamento (`subscriptions.type`).
 * I valori sono quelli di Directus.
 */
enum TipoAbbonamento: string
{
    use HaEtichetta;

    case Streaming = 'streaming';
    case Software = 'software';
    case Cloud = 'cloud';
    case Web = 'web';
    case Other = 'other';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Streaming => 'Streaming e intrattenimento',
            self::Software => 'Software e licenze',
            self::Cloud => 'Cloud e archiviazione',
            self::Web => 'Domini, hosting ed email',
            self::Other => 'Altro',
        };
    }
}
