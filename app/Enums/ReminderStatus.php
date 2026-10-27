<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The status of a reminder (`reminders.status`).
 * The values are those of Directus.
 */
enum ReminderStatus: string
{
    use HasLabel;

    case Open = 'open';
    case Done = 'done';
}
