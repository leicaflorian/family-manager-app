<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Where a consumption row comes from (`utility_consumptions.source`).
 */
enum ConsumptionSource: string
{
    use HasLabel;

    case Reading = 'reading';
    case Manual = 'manual';
}
