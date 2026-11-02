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
     */
    public function __construct(
        public readonly AgendaReminderState $state,
        public readonly ?string $next_notice,
    ) {}
}
