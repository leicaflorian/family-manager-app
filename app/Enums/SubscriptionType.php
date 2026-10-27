<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The type of a subscription (`subscriptions.type`).
 * The values are those of Directus.
 */
enum SubscriptionType: string
{
    use HasLabel;

    case Streaming = 'streaming';
    case Software = 'software';
    case Cloud = 'cloud';
    case Web = 'web';
    case Other = 'other';
}
