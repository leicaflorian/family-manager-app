<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * I tipi di un ente (`organizations.types`, più di uno per ente).
 * I valori sono quelli di Directus.
 */
enum TipoEnte: string
{
    use HaEtichetta;

    case Bank = 'bank';
    case Insurance = 'insurance';
    case UtilityProvider = 'utility_provider';
    case Employer = 'employer';
    case PublicOffice = 'public_office';
    case School = 'school';
    case Healthcare = 'healthcare';
    case Shop = 'shop';
    case Other = 'other';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Bank => 'Banca o finanziaria',
            self::Insurance => 'Assicurazione',
            self::UtilityProvider => 'Fornitore utenze',
            self::Employer => 'Datore di lavoro',
            self::PublicOffice => 'Ente pubblico',
            self::School => 'Scuola o associazione',
            self::Healthcare => 'Sanità',
            self::Shop => 'Negozio o servizio',
            self::Other => 'Altro',
        };
    }
}
