<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Lo stato di una pratica (`filings.status`).
 * I valori sono quelli di Directus.
 */
enum StatoPratica: string
{
    use HaEtichetta;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Draft => 'Bozza',
            self::Submitted => 'Inviata',
            self::Approved => 'Accolta',
            self::Rejected => 'Respinta',
        };
    }
}
