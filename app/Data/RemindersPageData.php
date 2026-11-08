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
     * @param  list<PersonData>  $people  the people of the family, for the filter of the person
     * @param  int|null  $person_id  the person chosen in *Who*
     * @param  int  $open_total  how many reminders of the whole family are still to do, whatever the filter, for the tab
     * @param  list<OptionData>  $areas  the areas in the order of the board, for the menu of the areas
     * @param  list<string>  $family_hidden  the values of the areas the family hid
     */
    public function __construct(
        public readonly array $open,
        public readonly array $done,
        public readonly array $people,
        public readonly ?int $person_id,
        public readonly int $open_total,
        public readonly array $areas = [],
        public readonly array $family_hidden = [],
    ) {}
}
