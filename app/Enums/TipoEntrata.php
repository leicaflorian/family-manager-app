<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Il tipo di un'entrata, incassata o prevista (`incomes.type`, `planned_incomes.type`).
 * I valori sono quelli di Directus.
 */
enum TipoEntrata: string
{
    use HaEtichetta;

    case Salary = 'salary';
    case Benefit = 'benefit';
    case Refund = 'refund';
    case Other = 'other';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Salary => 'Stipendio',
            self::Benefit => 'INPS e bonus',
            self::Refund => 'Rimborso',
            self::Other => 'Altro',
        };
    }
}
