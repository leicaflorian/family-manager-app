<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Enums\SubscriptionStatus;
use App\Models\PlannedExpense;
use App\Models\Subscription;
use App\Support\Reminders\Renewals;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Moves the renewal of a subscription on by one period of its fee. It is the one place both gestures that confirm a
 * renewal go through: marking its reminder as done and recording a payment of its fee.
 */
final class RenewSubscription
{
    /**
     * The fee the renewal of a subscription follows, among the planned expenses of the same card.
     *
     * @param  Subscription  $subscription  the subscription
     */
    public function feeOf(Subscription $subscription): ?PlannedExpense
    {
        /** @var Collection<int, PlannedExpense> $fees */
        $fees = PlannedExpense::query()
            ->where('card_type', $subscription->getMorphClass())
            ->where('card_id', $subscription->id)
            ->get();

        return Renewals::fee($fees);
    }

    /**
     * Moves the renewal from a date to one period later. Only while the subscription is active and its date is still
     * that one: changed by hand meanwhile, or moved already by the other gesture, it is left alone.
     *
     * @param  int  $subscriptionId  the subscription
     * @param  CarbonImmutable  $from  the renewal date being confirmed
     * @return bool whether the renewal moved
     */
    public function handle(int $subscriptionId, CarbonImmutable $from): bool
    {
        // Locked, so that two gestures at once cannot both read the old date and move it.
        $subscription = Subscription::query()->lockForUpdate()->find($subscriptionId);

        if (! $subscription instanceof Subscription
            || $subscription->status !== SubscriptionStatus::Active
            || $subscription->renewal_date?->toDateString() !== $from->toDateString()) {
            return false;
        }

        $fee = $this->feeOf($subscription);

        if (! $fee instanceof PlannedExpense) {
            return false;
        }

        $next = Renewals::next($from, $fee->interval, $fee->interval_unit->value);

        if (! $next instanceof CarbonImmutable) {
            return false;
        }

        $subscription->update(['renewal_date' => $next]);

        return true;
    }
}
