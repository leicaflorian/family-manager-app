<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The page *Reminders*: the open ones and the done ones, and who they are filtered by.
 */
#[TypeScript]
final class RemindersPageData extends Data
{
    /**
     * @param  list<ReminderData>  $open  overdue first, then the coming ones, by date
     * @param  list<ReminderData>  $done  the most recent first
     * @param  list<OptionData>  $people  the people of the family, for *Who*
     * @param  int|null  $person_id  the person chosen in *Who*
     */
    public function __construct(
        public readonly array $open,
        public readonly array $done,
        public readonly array $people,
        public readonly ?int $person_id,
    ) {}
}
