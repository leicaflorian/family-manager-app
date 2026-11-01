<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reminders\MarkReminderDone;
use App\Actions\Reminders\ReopenReminder;
use App\Models\Reminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Marking a reminder done and reopening it: whoever sees it can, an automatic one included.
 */
final class ReminderDoneController extends Controller
{
    /**
     * Marks the reminder done; the renewal of a subscription also moves its date.
     *
     * @throws ValidationException if the renewal cannot be confirmed
     */
    public function store(Reminder $reminder, MarkReminderDone $done): RedirectResponse
    {
        Gate::authorize('markDone', $reminder);

        $done->handle($reminder);

        return back();
    }

    /**
     * Reopens the reminder.
     */
    public function destroy(Reminder $reminder, ReopenReminder $reopen): RedirectResponse
    {
        Gate::authorize('markDone', $reminder);

        $reopen->handle($reminder);

        return back();
    }
}
