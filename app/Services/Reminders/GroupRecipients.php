<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Models\Person;
use App\Models\ReminderGroup;
use App\Models\User;
use App\Support\CurrentFamily;
use App\Support\Reminders\Notices;
use App\Support\Reminders\NotificationRule;
use Illuminate\Support\Collection;

/**
 * Who gets the notices of a group, by name, with the same rules the morning run uses (`Notices::deliveriesOf()`).
 */
final class GroupRecipients
{
    /** @var Collection<int, User>|null */
    private ?Collection $accounts = null;

    /**
     * The first names of the accounts that get at least one notice of the rules.
     *
     * @param  list<NotificationRule>  $rules  the rules of a group
     * @return list<string>
     */
    public function of(array $rules): array
    {
        $names = [];

        foreach (Notices::deliveriesOf($rules, $this->accounts()) as $delivery) {
            $names[] = $delivery->user->person instanceof Person ? $delivery->user->person->first_name : $delivery->user->name;
        }

        return $names;
    }

    /**
     * The recipients of a group.
     *
     * @return list<string>
     */
    public function ofGroup(ReminderGroup $group): array
    {
        return $this->of(NotificationRule::listOf($group->notifications));
    }

    /**
     * The recipients of the default group, or of the fallback rules when the family has none.
     *
     * @param  Collection<int, ReminderGroup>  $groups  the groups of the family
     * @return list<string>
     */
    public function ofDefault(Collection $groups): array
    {
        $default = $groups->first(static fn (ReminderGroup $group): bool => $group->is_default);

        return $default instanceof ReminderGroup ? $this->ofGroup($default) : $this->of(Notices::fallback());
    }

    /**
     * When a group notifies: the offsets of its rules, from the earliest.
     *
     * @return list<int>
     */
    public function offsetsOfGroup(ReminderGroup $group): array
    {
        return $this->offsets(NotificationRule::listOf($group->notifications));
    }

    /**
     * When the default group (or the fallback rules) notifies.
     *
     * @param  Collection<int, ReminderGroup>  $groups  the groups of the family
     * @return list<int>
     */
    public function offsetsOfDefault(Collection $groups): array
    {
        $default = $groups->first(static fn (ReminderGroup $group): bool => $group->is_default);

        return $default instanceof ReminderGroup ? $this->offsetsOfGroup($default) : $this->offsets(Notices::fallback());
    }

    /**
     * The distinct offsets of some rules, the earliest first.
     *
     * @param  list<NotificationRule>  $rules  the rules
     * @return list<int>
     */
    private function offsets(array $rules): array
    {
        $offsets = array_values(array_unique(array_map(static fn (NotificationRule $rule): int => $rule->offset, $rules)));
        sort($offsets);

        return $offsets;
    }

    /**
     * The accounts of the family, with their person, read once.
     *
     * @return Collection<int, User>
     */
    private function accounts(): Collection
    {
        return $this->accounts ??= User::query()->where('family_id', CurrentFamily::id())->with('person')->orderBy('id')->get();
    }
}
