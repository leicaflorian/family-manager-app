<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The alert groups a new family starts with, as on the board *Impostazioni → Avvisi*: every notice goes to everybody.
 * The names are English translation keys, written in the language of whoever registers (like `DefaultCategories`),
 * and the shape is the saved one of Directus (`{name, days, timing, channels, recipients}`).
 */
final class DefaultReminderGroups
{
    /**
     * The groups, in display order. The last one is the default: the kinds of deadline that are in no group.
     *
     * @return list<array{name: string, is_default: bool, deadline_types: list<string>, notifications: list<array{name: string, days: int, timing: string, channels: string, recipients: list<string>}>}>
     */
    public static function all(): array
    {
        return [
            self::group('Policies', ['insurance_policies.expiry_date'], [[45, 'before'], [15, 'before'], [0, 'before']]),
            self::group('Vehicles', ['vehicles.road_tax_due_date', 'vehicles.inspection_due_date'], [[45, 'before'], [15, 'before'], [0, 'before'], [7, 'after']]),
            self::group('Documents and health', ['documents.expiry_date', 'health_events.next_checkup_date'], [[90, 'before', 'email'], [7, 'before'], [1, 'before']]),
            self::group('Subscriptions', ['subscriptions.trial_end_date', 'subscriptions.renewal_date'], [[3, 'before', 'app'], [1, 'before', 'app']]),
            self::group('Default', [], [[7, 'before'], [1, 'before'], [0, 'before']], true),
        ];
    }

    /**
     * One group.
     *
     * @param  string  $name  the English name
     * @param  list<string>  $types  the kinds of deadline, as `collection.field`
     * @param  list<array{0: int, 1: string, 2?: string}>  $notices  days, timing and channels (both by default)
     * @param  bool  $default  whether it is the default group
     * @return array{name: string, is_default: bool, deadline_types: list<string>, notifications: list<array{name: string, days: int, timing: string, channels: string, recipients: list<string>}>}
     */
    private static function group(string $name, array $types, array $notices, bool $default = false): array
    {
        return [
            'name' => __($name),
            'is_default' => $default,
            'deadline_types' => $types,
            'notifications' => array_map(static fn (array $notice): array => [
                'name' => __('Reminder'),
                'days' => $notice[0],
                'timing' => $notice[1],
                'channels' => $notice[2] ?? 'both',
                'recipients' => ['all'],
            ], $notices),
        ];
    }
}
