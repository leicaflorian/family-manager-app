<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Il tipo di un'utenza (`utilities.type`).
 * I valori sono quelli di Directus.
 */
enum TipoUtenza: string
{
    use HaEtichetta;

    case Electricity = 'electricity';
    case Gas = 'gas';
    case Water = 'water';
    case Internet = 'internet';
    case Phone = 'phone';
    case WasteTax = 'waste_tax';
    case TvLicense = 'tv_license';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Electricity => 'Luce',
            self::Gas => 'Gas',
            self::Water => 'Acqua',
            self::Internet => 'Internet',
            self::Phone => 'Telefonia',
            self::WasteTax => 'TARI',
            self::TvLicense => 'Canone RAI',
        };
    }
}
