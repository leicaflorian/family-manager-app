<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The automatic reminder of a deadline in the Agenda: still to do, to do with its day passed, or done.
 */
enum AgendaReminderState: string
{
    use HasLabel;

    case Open = 'open';
    case Late = 'late';
    case Done = 'done';
}
