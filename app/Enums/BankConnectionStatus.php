<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Where a connection to a bank is (`bank_connections.status`).
 */
enum BankConnectionStatus: string
{
    use HasLabel;

    case Active = 'active';
    case NeedsReconnect = 'needs_reconnect';
    case Error = 'error';
}
