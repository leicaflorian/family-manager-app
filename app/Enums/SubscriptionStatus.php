<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The status of a subscription (`subscriptions.status`).
 * The values are those of Directus.
 */
enum SubscriptionStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Cancelled = 'cancelled';
}
