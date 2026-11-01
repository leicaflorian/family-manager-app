<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Reminders\RenewSubscription;
use App\Enums\ReminderStatus;
use App\Models\Expense;
use App\Models\PlannedExpense;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Support\Reminders\Renewals;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A payment of the fee a subscription renews with confirms the renewal due shortly after it: the reminder is marked
 * as done and the renewal date moves on by one period (`Renewals::paymentRenews`).
 */
final readonly class ExpenseRenewalObserver
{
    /**
     * @param  RenewSubscription  $renewals  Moves the renewal of a subscription.
     */
    public function __construct(private RenewSubscription $renewals) {}

    /**
     * An expense was created or changed. Only a new one, or one that changed its fee or its day, can confirm a renewal.
     *
     * @param  Expense  $expense  the expense
     */
    public function saved(Expense $expense): void
    {
        if (! $expense->wasRecentlyCreated && ! $expense->wasChanged(['planned_expense_id', 'date'])) {
            return;
        }

        $fee = $expense->plannedExpense;
        $paid = $expense->date;

        if (! $fee instanceof PlannedExpense || ! $paid instanceof CarbonImmutable || $fee->card_type !== (new Subscription)->getMorphClass()) {
            return;
        }

        $subscription = Subscription::query()->find($fee->card_id);

        // Only the fee the renewal follows: a monthly add-on does not renew the yearly subscription.
        if (! $subscription instanceof Subscription || $subscription->renewal_date === null || $this->renewals->feeOf($subscription)?->id !== $fee->id) {
            return;
        }

        $renewal = CarbonImmutable::parse($subscription->renewal_date);

        if (! Renewals::paymentRenews($renewal, $paid, $fee->interval, $fee->interval_unit->value)) {
            return;
        }

        DB::transaction(function () use ($subscription, $renewal): void {
            if ($this->renewals->handle($subscription->id, $renewal)) {
                $this->markDone($subscription, $renewal);
            }
        });
    }

    /**
     * Marks as done the open reminder of the renewal that has just been confirmed.
     *
     * @param  Subscription  $subscription  the subscription
     * @param  CarbonImmutable  $renewal  the renewal date that was confirmed
     */
    private function markDone(Subscription $subscription, CarbonImmutable $renewal): void
    {
        foreach (Reminder::query()->automaticFor(Renewals::FIELD, $subscription->getMorphClass(), [$subscription->id])->open()->get() as $reminder) {
            if ($reminder->due_date->toDateString() === $renewal->toDateString()) {
                $reminder->update(['status' => ReminderStatus::Done]);
            }
        }
    }
}
