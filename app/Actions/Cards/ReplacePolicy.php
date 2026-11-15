<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use App\Enums\InsurancePolicyStatus;
use App\Enums\ReminderStatus;
use App\Models\Expense;
use App\Models\InsurancePolicy;
use App\Models\PlannedExpense;
use App\Models\Reminder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Replaces old insurance policies with a new one: the old ones are cancelled, their renewal reminder is closed, their
 * premium moves to the new policy (or ends when the new one has its own) and the payment just made moves too. The
 * caller opens the transaction and has checked permissions and family.
 */
final readonly class ReplacePolicy
{
    /** The `source_field` of the automatic renewal reminder of a policy. */
    private const string EXPIRY_FIELD = 'insurance_policies.expiry_date';

    /**
     * Replaces the old policies.
     *
     * @param  InsurancePolicy  $new  the policy that takes over
     * @param  Collection<int, InsurancePolicy>  $old  the policies replaced
     * @param  Expense|null  $payment  the payment just made, moved to the new policy; older ones stay where they are
     */
    public function handle(InsurancePolicy $new, Collection $old, ?Expense $payment = null): void
    {
        foreach ($old as $policy) {
            $this->closeReminders($policy);
            $this->movePremiums($new, $policy);
            $policy->forceFill(['status' => InsurancePolicyStatus::Cancelled])->save();
        }

        $payment?->forceFill(['card_type' => $new->getMorphClass(), 'card_id' => $new->id])->save();
    }

    /**
     * Marks done the open renewal reminders of a policy. It happens before the save: the reminder sync removes an
     * open reminder of a card that is no longer active (see `UpdateDeadline`).
     *
     * @param  InsurancePolicy  $policy  the policy being replaced
     */
    private function closeReminders(InsurancePolicy $policy): void
    {
        Reminder::query()->automaticFor(self::EXPIRY_FIELD, $policy->getMorphClass(), [$policy->id])
            ->where('status', ReminderStatus::Open)
            ->get()
            ->each(static fn (Reminder $reminder): bool => $reminder->forceFill(['status' => ReminderStatus::Done])->save());
    }

    /**
     * Moves the planned expenses of the old policy to the new one, or, when the new one already has a premium of its
     * own, ends them on the day the old policy expires (today when it has no date) and keeps their link.
     *
     * @param  InsurancePolicy  $new  the policy that takes over
     * @param  InsurancePolicy  $old  the policy being replaced
     */
    private function movePremiums(InsurancePolicy $new, InsurancePolicy $old): void
    {
        $type = $new->getMorphClass();
        $newHasOwn = PlannedExpense::query()->where('card_type', $type)->where('card_id', $new->id)->exists();
        $end = ($old->expiry_date ?? CarbonImmutable::today('Europe/Rome'))->toDateString();

        foreach (PlannedExpense::query()->where('card_type', $type)->where('card_id', $old->id)->get() as $premium) {
            $premium->forceFill($newHasOwn ? ['end_date' => $end] : ['card_type' => $type, 'card_id' => $new->id])->save();
        }
    }
}
