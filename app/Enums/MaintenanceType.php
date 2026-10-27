<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The type of a maintenance record (`maintenance_records.type`).
 * The values are those of Directus.
 */
enum MaintenanceType: string
{
    use HasLabel;

    case Service = 'service';
    case Inspection = 'inspection';
    case Repair = 'repair';
    case BoilerService = 'boiler_service';
    case Other = 'other';
}
