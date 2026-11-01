<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Enums\ReminderStatus;
use App\Models\Reminder;
use Illuminate\Support\Facades\DB;

/**
 * Saves a reminder written by hand with the thing it refers to.
 */
final class SaveReminder
{
    /**
     * Creates the reminder or changes it, and replaces its link, in one transaction.
     *
     * @param  Reminder|null  $reminder  the reminder to change, null for a new one
     * @param  array{title: string, due_date: string, person_id: ?int, reminder_group_id: ?int}  $fields  the columns
     * @param  array{type: string, id: int}|null  $subject  the thing it refers to, null for none
     */
    public function handle(?Reminder $reminder, array $fields, ?array $subject): Reminder
    {
        return DB::transaction(function () use ($reminder, $fields, $subject): Reminder {
            if ($reminder instanceof Reminder) {
                $reminder->update($fields);
            } else {
                $reminder = Reminder::query()->create([...$fields, 'status' => ReminderStatus::Open, 'is_automatic' => false]);
            }

            $reminder->link()->delete();

            if ($subject !== null) {
                $reminder->link()->create(['remindable_type' => $subject['type'], 'remindable_id' => $subject['id']]);
            }

            return $reminder;
        });
    }
}
