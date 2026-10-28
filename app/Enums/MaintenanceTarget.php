<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * What a maintenance refers to: one thing only, a home, a belonging or a
 * vehicle. It is not a column: the form asks it first, then the card.
 */
enum MaintenanceTarget: string
{
    use HasLabel;

    case Home = 'home';
    case Belonging = 'belonging';
    case Vehicle = 'vehicle';
}
