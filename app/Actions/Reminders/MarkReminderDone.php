<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Support\Reminders\Renewals;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marks a reminder as done. The reminder of the renewal of a subscription also moves the renewal date on by one
 * period of the fee; when it cannot move, nothing changes and the reminder stays open.
 */
final readonly class MarkReminderDone
{
    /**
     * @param  RenewSubscription  $renewals  Moves the renewal of a subscription.
     */
    public function __construct(private RenewSubscription $renewals) {}

    /**
     * Marks the reminder as done, in one transaction.
     *
     * @param  Reminder  $reminder  the reminder
     *
     * @throws ValidationException if the renewal of its subscription cannot be moved
     */
    public function handle(Reminder $reminder): void
    {
        DB::transaction(function () use ($reminder): void {
            if ($reminder->status === ReminderStatus::Open && $reminder->is_automatic && $reminder->source_field === Renewals::FIELD) {
                $this->renew($reminder);
            }

            // The reminder is done before the sync that follows the move of the renewal, which never touches a done one.
            $reminder->update(['status' => ReminderStatus::Done]);
        });
    }

    /**
     * Moves the renewal of the subscription of a renewal reminder from the date of the reminder.
     *
     * @param  Reminder  $reminder  the reminder of a renewal
     *
     * @throws ValidationException if the renewal cannot be moved
     */
    private function renew(Reminder $reminder): void
    {
        $subscription = $reminder->link?->subject;

        if ($subscription instanceof Subscription && $this->renewals->handle($subscription->id, CarbonImmutable::parse($reminder->due_date))) {
            return;
        }

        throw ValidationException::withMessages(['status' => Renewals::notRenewedMessage([$subscription instanceof Subscription ? $subscription->name : '#'.$reminder->id])]);
    }
}
