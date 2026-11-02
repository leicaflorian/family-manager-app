<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reminders\PostponeReminder;
use App\Http\Requests\Reminders\PostponeReminderRequest;
use App\Models\Reminder;
use Illuminate\Http\RedirectResponse;

/**
 * *Postpone* of a reminder: whoever may mark it done may put it off, an automatic one included.
 */
final class ReminderPostponeController extends Controller
{
    /**
     * Puts the reminder off to the day of the request.
     *
     * @param  PostponeReminderRequest  $request  the new day
     * @param  Reminder  $reminder  the reminder
     * @param  PostponeReminder  $postpone  the action
     */
    public function __invoke(PostponeReminderRequest $request, Reminder $reminder, PostponeReminder $postpone): RedirectResponse
    {
        $postpone->handle($reminder, $request->day());

        return back();
    }
}
