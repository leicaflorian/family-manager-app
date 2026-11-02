<?php

declare(strict_types=1);

namespace App\Support\Reminders;

use App\Cards\DeadlineSource;
use App\Cards\DeadlineSources;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * The checks of the groups of notices before they are saved (port of `editsOf` and `checkGroups` of Directus). The
 * page sends the whole list as it should be left, so a group missing from it is a group to delete.
 */
final class GroupRules
{
    /**
     * The changes a save makes: groups to create (no id), to change (with an id) and the ids to delete.
     *
     * @param  list<array<string, mixed>>  $groups  the groups as they should be left
     * @param  list<int>  $currentIds  the ids of the groups there are now
     * @return array{create: list<array<string, mixed>>, update: list<array<string, mixed>>, delete: list<int>}
     */
    public static function edits(array $groups, array $currentIds): array
    {
        $create = [];
        $update = [];
        $kept = [];

        foreach ($groups as $group) {
            $id = self::idOf($group);

            if ($id === null) {
                $create[] = $group;
            } else {
                $update[] = $group;
                $kept[] = $id;
            }
        }

        return ['create' => $create, 'update' => $update, 'delete' => array_values(array_diff($currentIds, $kept))];
    }

    /**
     * The groups as they are saved: only the kinds of deadline that exist, and the notices in the saved shape.
     * A notice that cannot be read throws, so check first.
     *
     * @param  list<array<string, mixed>>  $groups  the groups as sent
     * @param  list<string>  $fields  the kinds of deadline that exist, as `collection.field`
     * @return list<array{id: int|null, name: string, is_default: bool, deadline_types: list<string>, notifications: list<array{name: string, days: int, timing: string, channels: string, recipients: list<string|int>}>}>
     *
     * @throws InvalidArgumentException when a notice is not valid
     */
    public static function clean(array $groups, array $fields): array
    {
        return array_map(static fn (array $group): array => [
            'id' => self::idOf($group),
            'name' => self::nameOf($group),
            'is_default' => (bool) ($group['is_default'] ?? false),
            'deadline_types' => self::typesOf($group, $fields),
            'notifications' => NotificationRule::check($group['notifications'] ?? null),
        ], $groups);
    }

    /**
     * The errors of the groups as a save would leave them, for the person. Empty when all is well:
     * - the notices of a group are valid (whole days from 0 up, a recipient, Everybody or people);
     * - a kind of deadline is in one group only, as nobody could tell which notices count;
     * - there is one default group, the one of the kinds that are in no group.
     *
     * @param  list<array<string, mixed>>  $groups  the groups as they should be left
     * @param  list<string>  $fields  the kinds of deadline that exist, as `collection.field`
     * @return list<string>
     */
    public static function check(array $groups, array $fields): array
    {
        $errors = [];
        $holder = [];
        $defaults = [];

        foreach ($groups as $index => $group) {
            try {
                NotificationRule::check($group['notifications'] ?? null);
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }

            foreach (self::typesOf($group, $fields) as $type) {
                if (isset($holder[$type]) && $holder[$type] !== $index) {
                    $errors[] = __(':type is both in the group :first and in :second: a kind of deadline is in one group only.', [
                        'type' => self::typeName($type),
                        'first' => self::label($groups[$holder[$type]]),
                        'second' => self::label($group),
                    ]);
                }
                $holder[$type] ??= $index;
            }

            if ($group['is_default'] ?? false) {
                $defaults[] = $group;
            }
        }

        if (count($defaults) > 1) {
            $errors[] = __('The default group is only one: choose between :groups.', ['groups' => Arr::join(array_map(self::label(...), $defaults), ', ', __(' and '))]);
        } elseif ($defaults === []) {
            $errors[] = __('There must always be a default group: the one of the deadlines that are in no group. To change it, mark another group as the default.');
        }

        return $errors;
    }

    /**
     * The id of a group, null for a new one.
     *
     * @param  array<string, mixed>  $group  the group
     */
    private static function idOf(array $group): ?int
    {
        $id = $group['id'] ?? null;

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    /**
     * The name of a group, trimmed.
     *
     * @param  array<string, mixed>  $group  the group
     */
    private static function nameOf(array $group): string
    {
        return is_scalar($group['name'] ?? null) ? trim((string) $group['name']) : '';
    }

    /**
     * The name of a group between quotes, for the messages.
     *
     * @param  array<string, mixed>  $group  the group
     */
    private static function label(array $group): string
    {
        $name = self::nameOf($group);

        return '«'.($name !== '' ? $name : __('Unnamed')).'»';
    }

    /**
     * The kinds of deadline of a group that exist, once each.
     *
     * @param  array<string, mixed>  $group  the group
     * @param  list<string>  $fields  the kinds of deadline that exist
     * @return list<string>
     */
    private static function typesOf(array $group, array $fields): array
    {
        $types = is_array($group['deadline_types'] ?? null) ? $group['deadline_types'] : [];

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $type): string => is_scalar($type) ? (string) $type : '', $types),
            static fn (string $type): bool => in_array($type, $fields, true),
        )));
    }

    /**
     * What the kind of deadline is called, for the messages.
     *
     * @param  string  $field  the key, like `vehicles.road_tax_due_date`
     */
    public static function typeName(string $field): string
    {
        $source = DeadlineSources::find($field);

        return $source instanceof DeadlineSource ? ($source->label ?? __('Document expiry')) : $field;
    }
}
