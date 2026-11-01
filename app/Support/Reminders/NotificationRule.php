<?php

declare(strict_types=1);

namespace App\Support\Reminders;

use App\Enums\Channel;
use InvalidArgumentException;

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

    /** The recipient that means "every person with an account". */
    public const string ALL = 'all';

    /** The name of a row saved without one. */
    private const string DEFAULT_NAME = 'Promemoria';

    /**
     * The rules of the saved notifications of a group, as `notificationsOf` of Directus: ids as text become
     * numbers, an unknown channel is both, nothing is an empty list. A row that cannot be read is left out,
     * not fatal.
     *
     * @param  mixed  $json  the `notifications` column
     * @return list<NotificationRule>
     */
    public static function listOf(mixed $json): array
    {
        $rules = [];
        foreach (is_array($json) ? $json : [] as $row) {
            try {
                $saved = self::check([$row])[0];
            } catch (InvalidArgumentException) {
                continue;
            }

            $people = array_map(intval(...), $saved['recipients']);
            $rules[] = new self(
                $saved['timing'] === 'after' ? $saved['days'] : -$saved['days'],
                Channel::read($saved['channels']),
                in_array(self::ALL, $saved['recipients'], true) ? null : $people,
            );
        }

        return $rules;
    }

    /**
     * The notifications of a group, checked before they are saved (`checkNotifications` of Directus): whole
     * days from 0 up, and at least one recipient, either everybody or some people.
     *
     * @param  mixed  $value  the notifications as sent
     * @return list<array{name: string, days: int, timing: string, channels: string, recipients: list<string|int>}>
     *
     * @throws InvalidArgumentException with the message for the person
     */
    public static function check(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException(__('The notifications of the group are not a list.'));
        }

        $saved = [];
        foreach ($value as $index => $raw) {
            $row = is_array($raw) ? $raw : [];
            $name = is_scalar($row['name'] ?? null) ? trim((string) $row['name']) : '';
            $label = $name !== '' ? '«'.$name.'»' : __('Notification :number', ['number' => $index + 1]);

            $days = $row['days'] ?? null;
            $wholeDays = self::whole($days);
            if ($wholeDays === null || $wholeDays < 0) {
                throw new InvalidArgumentException(__(':label: days must be a whole number, from 0 up (0 is the day of the deadline).', ['label' => $label]));
            }

            $chosen = is_array($row['recipients'] ?? null) ? array_values($row['recipients']) : [];
            if ($chosen === []) {
                throw new InvalidArgumentException(__(':label: the recipient is missing. Choose Everybody or at least one person.', ['label' => $label]));
            }
            $everybody = in_array(self::ALL, $chosen, true);
            if ($everybody && count($chosen) > 1) {
                throw new InvalidArgumentException(__(':label: Everybody and specific people exclude each other. Choose Everybody or the people.', ['label' => $label]));
            }

            $people = [];
            if (! $everybody) {
                foreach ($chosen as $id) {
                    $personId = self::whole($id);
                    if ($personId === null || $personId <= 0) {
                        throw new InvalidArgumentException(__(':label: a recipient is not a person.', ['label' => $label]));
                    }
                    $people[] = $personId;
                }
                $people = array_values(array_unique($people));
            }

            $channels = $row['channels'] ?? null;
            $saved[] = [
                'name' => $name !== '' ? $name : self::DEFAULT_NAME,
                'days' => $wholeDays,
                'timing' => ($row['timing'] ?? null) === 'after' && $wholeDays > 0 ? 'after' : 'before',
                'channels' => Channel::read($channels)->value,
                'recipients' => $everybody ? [self::ALL] : $people,
            ];
        }

        return $saved;
    }

    /**
     * A whole number sent as a number or as text, or null when it is anything else.
     *
     * @param  mixed  $value  the value as sent
     */
    private static function whole(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : null;
    }
}
