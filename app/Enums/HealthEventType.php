<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The type of a health event (`health_events.type`).
 * The values are those of Directus.
 */
enum HealthEventType: string
{
    use HasLabel;

    case Visit = 'visit';
    case Exam = 'exam';
    case Vaccine = 'vaccine';
    case Prescription = 'prescription';
    case Optical = 'optical';
}
