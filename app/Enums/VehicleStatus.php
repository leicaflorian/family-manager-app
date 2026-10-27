<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The status of a vehicle (`vehicles.status`).
 * The values are those of Directus.
 */
enum VehicleStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Sold = 'sold';
}
