<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The areas of family life that group the cards, in the order of the canvas.
 * The values are the keys of `resources/js/lib/areas.ts`, without `general`.
 */
enum Area: string
{
    use HasLabel;

    case Home = 'home';
    case Vehicles = 'vehicles';
    case Insurance = 'insurance';
    case Banks = 'banks';
    case Health = 'health';
    case School = 'school';
    case Work = 'work';
    case Subscriptions = 'subscriptions';
    case Paperwork = 'paperwork';
}
