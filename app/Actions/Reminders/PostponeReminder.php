<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Models\Reminder;
use Carbon\CarbonImmutable;

/**
 * *Postpone*: puts a reminder off to another day. A reminder written by hand is its own deadline, so its day
 * moves (and its form shows the new one). An automatic one keeps the deadline of its card in `due_date`, which
 * the sync matches it by, and gets the new day in `postponed_to`; put back to the day of the deadline it is no
 * longer postponed. The list, the notices and the calendar read {@see Reminder::day()}.
 */
final readonly class PostponeReminder
{
    /**
     * Moves the reminder to a day.
     *
     * @param  Reminder  $reminder  the reminder, still to do
     * @param  CarbonImmutable  $day  the new day
     */
    public function handle(Reminder $reminder, CarbonImmutable $day): void
    {
        if ($reminder->is_automatic) {
            $reminder->postponed_to = $day->isSameDay($reminder->due_date) ? null : $day;
        } else {
            $reminder->due_date = $day;
            $reminder->postponed_to = null;
        }

        $reminder->save();
    }
}
