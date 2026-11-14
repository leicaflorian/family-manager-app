<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\AgendaReminderState;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The automatic reminder of a deadline in the Agenda: whether it is still to do,
 * late or done, and when its next notice goes out.
 */
#[TypeScript]
final class AgendaReminderData extends Data
{
    /**
     * @param  AgendaReminderState  $state  still to do, to do with its day passed, or done
     * @param  string|null  $next_notice  the day of the next notice, `yyyy-mm-dd`; null when none is left
     * @param  int  $id  the id of the reminder
     * @param  list<string>  $notice_days  the days of all the notices of the current date, `yyyy-mm-dd`, by date
     * @param  list<string>  $recipients  the names of the accounts that receive them
     * @param  string|null  $done_on  the day it was marked done, `yyyy-mm-dd`; null while it is not done
     * @param  string|null  $done_by  the name of the account that marked it done
     * @param  bool  $can_edit  whether whoever looks may change it
     * @param  bool  $can_delete  whether whoever looks may delete it
     */
    public function __construct(
        public readonly AgendaReminderState $state,
        public readonly ?string $next_notice,
        public readonly int $id,
        public readonly array $notice_days = [],
        public readonly array $recipients = [],
        public readonly ?string $done_on = null,
        public readonly ?string $done_by = null,
        public readonly bool $can_edit = false,
        public readonly bool $can_delete = false,
    ) {}
}
