<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Models\ReminderGroup;
use App\Support\Reminders\GroupRules;
use Illuminate\Support\Facades\DB;

/**
 * Saves the groups of notices of the page: the whole list at once, in the JSON shape of Directus.
 */
final class SaveReminderGroups
{
    /**
     * Creates the new groups, changes the others and deletes the ones left out, in one transaction. A reminder of a
     * deleted group falls back to the default group (the foreign key empties its field).
     *
     * @param  list<array{id: int|null, name: string, is_default: bool, deadline_types: list<string>, notifications: list<array{name: string, days: int, timing: string, channels: string, recipients: list<string|int>}>}>  $groups  the groups as they should be left, already checked
     */
    public function handle(array $groups): void
    {
        DB::transaction(function () use ($groups): void {
            $current = ReminderGroup::query()->get();
            $edits = GroupRules::edits($groups, array_values(array_map(static fn (ReminderGroup $group): int => $group->id, $current->all())));

            ReminderGroup::query()->whereIn('id', $edits['delete'])->delete();

            foreach ($edits['update'] as $group) {
                $saved = $current->firstWhere('id', $group['id']);

                if ($saved instanceof ReminderGroup) {
                    $saved->update($this->columns($group));
                }
            }
            foreach ($edits['create'] as $group) {
                ReminderGroup::query()->create($this->columns($group));
            }
        });
    }

    /**
     * The columns of a group.
     *
     * @param  array<string, mixed>  $group  the group
     * @return array{name: mixed, is_default: mixed, deadline_types: mixed, notifications: mixed}
     */
    private function columns(array $group): array
    {
        return [
            'name' => $group['name'],
            'is_default' => $group['is_default'],
            'deadline_types' => $group['deadline_types'],
            'notifications' => $group['notifications'],
        ];
    }
}
