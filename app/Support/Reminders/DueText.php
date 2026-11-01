<?php

declare(strict_types=1);

namespace App\Support\Reminders;

use Carbon\CarbonImmutable;

/**
 * The words of a due date, for the morning mail, the bell and the notices of
 * the cards (`whenText` of Directus). Dates are written `gg/mm/aaaa` in both
 * languages.
 */
final class DueText
{
    /**
     * How far a due date is from today, in words.
     *
     * @param  CarbonImmutable  $today  today in Rome
     * @param  CarbonImmutable  $due  the due date
     * @param  bool  $manual  whether the reminder was written by hand (it says "by" instead of "due")
     * @return string e.g. "Due today", "Due in 7 days, on 21/10/2026", "By tomorrow"
     */
    public static function when(CarbonImmutable $today, CarbonImmutable $due, bool $manual): string
    {
        $days = self::daysBetween($today, $due);
        $date = $due->format('d/m/Y');

        return match (true) {
            $days === 0 => $manual ? __('By today') : __('Due today'),
            $days === 1 => $manual ? __('By tomorrow') : __('Due tomorrow'),
            $days > 1 => $manual ? __('By :date, in :count days', ['date' => $date, 'count' => $days]) : __('Due in :count days, on :date', ['count' => $days, 'date' => $date]),
            $days === -1 => __('Was due yesterday, on :date', ['date' => $date]),
            default => __('Was due :count days ago, on :date', ['count' => -$days, 'date' => $date]),
        };
    }

    /**
     * The start of the subject of the mail of a reminder.
     *
     * @param  CarbonImmutable  $today  today in Rome
     * @param  CarbonImmutable  $due  the due date
     * @return string "Reminder" before the day, "Due today" on it, "Overdue" after it
     */
    public static function subject(CarbonImmutable $today, CarbonImmutable $due): string
    {
        return match (self::daysBetween($today, $due) <=> 0) {
            1 => __('Reminder'),
            0 => __('Due today'),
            default => __('Overdue'),
        };
    }

    /**
     * The calendar days from one date to another. It counts the dates and not
     * the hours, so the change of the clock does not move a day; both are read
     * as the day in Rome, whatever the time zone they come in.
     *
     * @param  CarbonImmutable  $from  the first date
     * @param  CarbonImmutable  $to  the second date
     * @return int negative when `to` is before `from`
     */
    private static function daysBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) CarbonImmutable::parse($from->setTimezone('Europe/Rome')->format('Y-m-d'), 'UTC')->diffInDays(CarbonImmutable::parse($to->setTimezone('Europe/Rome')->format('Y-m-d'), 'UTC'), false);
    }
}
