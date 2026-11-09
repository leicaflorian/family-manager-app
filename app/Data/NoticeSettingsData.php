<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything *Settings → Notices* needs.
 */
#[TypeScript]
final class NoticeSettingsData extends Data
{
    /**
     * @param  list<ReminderGroupData>  $groups  the groups
     * @param  list<OptionData>  $kinds  the kinds of deadline, the value is `collection.field` and the label what expires
     * @param  list<ReminderPersonData>  $people  the people with an account, who can be chosen
     * @param  list<string>  $without_account  the first names of the people without an account, who get no notice
     * @param  string  $reminders_time  the time of the morning round, `HH:MM`, set in the owner panel
     * @param  bool  $can_change  whether who looks can change the groups (only an administrator)
     */
    public function __construct(
        public readonly array $groups,
        public readonly array $kinds,
        public readonly array $people,
        public readonly array $without_account,
        public readonly string $reminders_time,
        public readonly bool $can_change,
    ) {}
}
