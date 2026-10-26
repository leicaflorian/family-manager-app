<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Il tipo di un evento di salute (`health_events.type`).
 * I valori sono quelli di Directus.
 */
enum TipoEventoSalute: string
{
    use HaEtichetta;

    case Visit = 'visit';
    case Exam = 'exam';
    case Vaccine = 'vaccine';
    case Prescription = 'prescription';
    case Optical = 'optical';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Visit => 'Visita',
            self::Exam => 'Esame',
            self::Vaccine => 'Vaccino',
            self::Prescription => 'Ricetta',
            self::Optical => 'Ottica',
        };
    }
}
