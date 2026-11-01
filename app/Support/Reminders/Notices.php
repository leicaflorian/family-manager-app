<?php

declare(strict_types=1);

namespace App\Support\Reminders;

use App\Models\Reminder;
use App\Models\ReminderGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Which notices a reminder gets from its group, when, and to whom (port of `groups.ts` of Directus).
 */
final class Notices
{
    /**
     * Without a default group: a week before, the day before, the day, to everybody, in the app and by email.
     *
     * @return list<NotificationRule>
     */
    public static function fallback(): array
    {
        return array_map(static fn (int $offset): NotificationRule => new NotificationRule($offset, Channel::Both), [-7, -1, 0]);
    }

    /**
     * The rules of a reminder: an automatic one has the group of its kind of deadline, a manual one the group
     * chosen in its field; without one, the default group, and without that the fallback.
     *
     * @param  Reminder  $reminder  the reminder
     * @param  Collection<int, ReminderGroup>  $groups  the groups of the family
     * @return list<NotificationRule>
     */
    public static function rulesFor(Reminder $reminder, Collection $groups): array
    {
        $own = $reminder->is_automatic
            ? $groups->first(static fn (ReminderGroup $group): bool => in_array((string) $reminder->source_field, array_map(strval(...), array_filter($group->deadline_types ?? [], is_scalar(...))), true))
            : ($reminder->reminder_group_id === null ? null : $groups->first(static fn (ReminderGroup $group): bool => $group->id === $reminder->reminder_group_id));
        $group = $own ?? $groups->first(static fn (ReminderGroup $group): bool => $group->is_default);

        return $group instanceof ReminderGroup ? NotificationRule::listOf($group->notifications) : self::fallback();
    }

    /**
     * What goes out today for a reminder, and the keys to remember. Each notice goes once. When several days
     * have come together (server down, a card created close to its deadline) only the rules of the latest
     * day go out: the earlier ones are remembered as sent, not sent late.
     *
     * @param  CarbonImmutable  $deadline  the day of the deadline
     * @param  list<NotificationRule>  $rules  the rules of the reminder
     * @param  list<string>  $sent  the keys already sent
     * @param  CarbonImmutable  $today  today in Rome
     * @return array{send: list<NotificationRule>, sent: list<string>}
     */
    public static function due(CarbonImmutable $deadline, array $rules, array $sent, CarbonImmutable $today): array
    {
        $reached = [];
        foreach ($rules as $rule) {
            $day = $deadline->startOfDay()->addDays($rule->offset)->toDateString();
            if ($day <= $today->toDateString()) {
                $reached[] = ['day' => $day, 'key' => self::key($deadline, $rule), 'rule' => $rule];
            }
        }
        usort($reached, static fn (array $a, array $b): int => $a['day'] <=> $b['day']);

        $last = $reached === [] ? null : $reached[count($reached) - 1]['day'];
        $send = [];
        foreach ($reached as $notice) {
            if ($notice['day'] === $last && ! in_array($notice['key'], $sent, true)) {
                $send[] = $notice['rule'];
            }
        }

        return ['send' => $send, 'sent' => array_column($reached, 'key')];
    }

    /**
     * The key of a notice: the deadline carries it, so a reminder moved to a new date is notified again.
     *
     * @param  CarbonImmutable  $deadline  the day of the deadline
     * @param  NotificationRule  $rule  the rule
     */
    public static function key(CarbonImmutable $deadline, NotificationRule $rule): string
    {
        return $deadline->toDateString().':'.$rule->offset;
    }

    /**
     * One delivery per account, with the channels of all the rules summed. "Everybody" is every account of
     * the moment; of the chosen people those without an account are skipped, and when none is left the
     * notice goes to everybody. The person of an account is its `person` relation.
     *
     * @param  NotificationRule  $rule  the rule
     * @param  Collection<int, User>  $accounts  the accounts of the family, with `person` loaded
     * @return list<Delivery>
     */
    public static function deliveries(NotificationRule $rule, Collection $accounts): array
    {
        $chosen = $rule->people === null
            ? $accounts
            : $accounts->filter(static fn (User $user): bool => in_array($user->person?->id, $rule->people, true));

        return array_values(
            ($chosen->isEmpty() ? $accounts : $chosen)
                ->map(static fn (User $user): Delivery => new Delivery(
                    $user,
                    $rule->channels !== Channel::Email,
                    $rule->channels !== Channel::App,
                ))
                ->all(),
        );
    }

    /**
     * The deliveries of several rules, one per account with the channels summed.
     *
     * @param  list<NotificationRule>  $rules  the rules of a day
     * @param  Collection<int, User>  $accounts  the accounts of the family
     * @return list<Delivery>
     */
    public static function deliveriesOf(array $rules, Collection $accounts): array
    {
        $byUser = [];
        foreach ($rules as $rule) {
            foreach (self::deliveries($rule, $accounts) as $delivery) {
                $previous = $byUser[$delivery->user->id] ?? null;
                $byUser[$delivery->user->id] = new Delivery(
                    $delivery->user,
                    $delivery->app || ($previous instanceof Delivery && $previous->app),
                    $delivery->email || ($previous instanceof Delivery && $previous->email),
                );
            }
        }

        return array_values($byUser);
    }

    /**
     * The next notice of a reminder after today, for "First notice on / Notice on / No more notices", not
     * looking at what was sent.
     *
     * @param  Reminder  $reminder  the reminder
     * @param  list<NotificationRule>  $rules  its rules
     * @param  CarbonImmutable  $today  today in Rome
     */
    public static function next(Reminder $reminder, array $rules, CarbonImmutable $today): ?CarbonImmutable
    {
        $deadline = CarbonImmutable::parse($reminder->due_date->toDateString(), $today->timezone);
        $next = null;
        foreach ($rules as $rule) {
            $day = $deadline->addDays($rule->offset);
            if ($day->greaterThan($today->startOfDay()) && ($next === null || $day->lessThan($next))) {
                $next = $day;
            }
        }

        return $next;
    }
}
