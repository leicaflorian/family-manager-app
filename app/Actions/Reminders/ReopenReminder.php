<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Enums\ReminderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\PlannedExpense;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Support\Reminders\Renewals;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reopens a reminder that was done. The reminder of a renewal also brings the renewal date of its subscription back,
 * if it is still the one the confirmation set.
 */
final readonly class ReopenReminder
{
    /**
     * @param  RenewSubscription  $renewals  Finds the fee of a subscription.
     */
    public function __construct(private RenewSubscription $renewals) {}

    /**
     * Reopens the reminder, in one transaction.
     *
     * @param  Reminder  $reminder  the reminder
     */
    public function handle(Reminder $reminder): void
    {
        DB::transaction(function () use ($reminder): void {
            if ($reminder->status === ReminderStatus::Done && $reminder->is_automatic && $reminder->source_field === Renewals::FIELD) {
                $this->bringBack($reminder);
            }

            $reminder->update(['status' => ReminderStatus::Open]);
        });
    }

    /**
     * Puts the renewal of the subscription back on the date of the reminder, when it is the one the confirmation set.
     *
     * @param  Reminder  $reminder  the reminder of a renewal
     */
    private function bringBack(Reminder $reminder): void
    {
        $subscription = $reminder->link?->subject;

        if (! $subscription instanceof Subscription) {
            return;
        }

        $subscription = Subscription::query()->lockForUpdate()->find($subscription->id);
        $fee = $subscription instanceof Subscription ? $this->renewals->feeOf($subscription) : null;

        if (! $subscription instanceof Subscription || ! $fee instanceof PlannedExpense || $subscription->status !== SubscriptionStatus::Active || $subscription->renewal_date === null) {
            return;
        }

        $back = Renewals::reopened(CarbonImmutable::parse($reminder->due_date), CarbonImmutable::parse($subscription->renewal_date), $fee->interval, $fee->interval_unit->value);

        if ($back instanceof CarbonImmutable) {
            $subscription->update(['renewal_date' => $back]);
        }
    }
}
