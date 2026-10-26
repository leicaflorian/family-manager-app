<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Il tipo di una pratica (`filings.type`).
 * I valori sono quelli di Directus.
 */
enum TipoPratica: string
{
    use HaEtichetta;

    case TaxReturn = 'tax_return';
    case Isee = 'isee';
    case Benefit = 'benefit';
    case ParentalLeave = 'parental_leave';
    case Municipal = 'municipal';
    case Other = 'other';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::TaxReturn => '730 / dichiarazione dei redditi',
            self::Isee => 'ISEE',
            self::Benefit => 'Assegni e bonus',
            self::ParentalLeave => 'Maternità e congedi',
            self::Municipal => 'Pratica comunale',
            self::Other => 'Altro',
        };
    }
}
