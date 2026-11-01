<?php

declare(strict_types=1);

namespace App\Support\Reminders;

/**
 * A row of the notifications of a reminder group: how many days from the deadline, where and for whom.
 */
final readonly class NotificationRule
{
    /**
     * @param  int  $offset  days from the deadline: negative before it, 0 on the day, positive after it
     * @param  Channel  $channels  where the notice arrives
     * @param  list<int>|null  $people  the ids of the chosen people, or null for everybody
     */
    public function __construct(
        public int $offset,
        public Channel $channels,
        public ?array $people = null,
    ) {}

    /**
     * The rules of the saved notifications of a group, as `notificationsOf` of Directus: ids as text become
     * numbers, an unknown channel is both, nothing is an empty list. A row that cannot be read (no whole offset,
     * no recipient, everybody mixed with people, a recipient that is not a person) is left out, not fatal.
     *
     * @param  mixed  $json  the `notifications` column
     * @return list<NotificationRule>
     */
    public static function listOf(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $rules = [];
        foreach ($json as $row) {
            $rule = is_array($row) ? self::fromRow($row) : null;
            if ($rule instanceof self) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * One saved row, or null when it cannot be read.
     *
     * @param  array<array-key, mixed>  $row  the saved row
     */
    private static function fromRow(array $row): ?self
    {
        $offset = $row['offset'] ?? null;
        if (! is_int($offset) && (! is_string($offset) || preg_match('/^-?\d+$/', $offset) !== 1)) {
            return null;
        }

        $chosen = $row['recipients'] ?? null;
        if (! is_array($chosen) || $chosen === []) {
            return null;
        }

        $channels = Channel::read($row['channels'] ?? null);
        if (in_array('all', $chosen, true)) {
            return count($chosen) > 1 ? null : new self((int) $offset, $channels);
        }

        $people = [];
        foreach ($chosen as $id) {
            if (! is_int($id) && (! is_string($id) || preg_match('/^\d+$/', $id) !== 1)) {
                return null;
            }
            if ((int) $id <= 0) {
                return null;
            }
            $people[] = (int) $id;
        }

        return new self((int) $offset, $channels, array_values(array_unique($people)));
    }
}
