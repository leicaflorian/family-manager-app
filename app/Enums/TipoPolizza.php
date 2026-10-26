<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Il tipo di una polizza (`insurance_policies.type`).
 * I valori sono quelli di Directus.
 */
enum TipoPolizza: string
{
    use HaEtichetta;

    case Car = 'car';
    case Life = 'life';
    case Home = 'home';
    case Health = 'health';
    case Accident = 'accident';
    case Fund = 'fund';
    case Other = 'other';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Car => 'Auto',
            self::Life => 'Vita',
            self::Home => 'Casa',
            self::Health => 'Sanitaria',
            self::Accident => 'Infortuni',
            self::Fund => 'Fondo sanitario',
            self::Other => 'Altro',
        };
    }
}
