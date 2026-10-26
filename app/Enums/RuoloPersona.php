<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Chi è una persona nella famiglia (`people.role`).
 */
enum RuoloPersona: string
{
    use HaEtichetta;

    case Parent = 'parent';
    case Child = 'child';
    case Other = 'other';

    /**
     * Il ruolo come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Parent => 'Genitore',
            self::Child => 'Figlio/a',
            self::Other => 'Altro',
        };
    }
}
