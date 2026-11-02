<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ReminderGroup;
use App\Support\Reminders\NotificationRule;
use InvalidArgumentException;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A group of notices as *Settings → Notices* shows and edits it.
 */
#[TypeScript]
final class ReminderGroupData extends Data
{
    /**
     * @param  int|null  $id  the id of the group, null for one not saved yet
     * @param  string  $name  the name
     * @param  bool  $is_default  whether it is the group of everything that has none
     * @param  list<string>  $deadline_types  the kinds of deadline it covers, as `collection.field`
     * @param  list<NoticeRuleData>  $rules  its notices
     * @param  string|null  $example  a sentence with the next card of the group that expires, null when there is none
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly bool $is_default,
        public readonly array $deadline_types,
        public readonly array $rules,
        public readonly ?string $example,
    ) {}

    /**
     * Builds it from the group. A notice copied from Directus that cannot be read is left out.
     *
     * @param  ReminderGroup  $group  the group
     * @param  string|null  $example  the sentence of the example
     */
    public static function fromGroup(ReminderGroup $group, ?string $example): self
    {
        $rules = [];

        foreach (is_array($group->notifications) ? $group->notifications : [] as $row) {
            try {
                $saved = NotificationRule::check([$row])[0];
            } catch (InvalidArgumentException) {
                continue;
            }

            $rules[] = new NoticeRuleData($saved['name'], $saved['days'], $saved['timing'], $saved['channels'], $saved['recipients']);
        }

        $types = array_values(array_unique(array_map(strval(...), array_filter($group->deadline_types ?? [], is_scalar(...)))));

        return new self($group->id, $group->name, $group->is_default, $types, $rules, $example);
    }
}
