<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use App\Enums\InsurancePolicyStatus;
use App\Enums\ReminderStatus;
use App\Models\Expense;
use App\Models\InsurancePolicy;
use App\Models\PlannedExpense;
use App\Models\Reminder;
use App\Services\Deadlines;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Replaces old insurance policies with a new one: the old ones are cancelled, their renewal reminder is closed, their
 * premium moves to the new policy (or ends when the new one has its own) and the payment just made moves too. The
 * caller opens the transaction and has checked permissions and family.
 */
final readonly class ReplacePolicy
{
    /**
     * @param  Deadlines  $deadlines  tells whether a due date is paid
     */
    public function __construct(private Deadlines $deadlines) {}

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
     * own, ends them and keeps their link. A series end is inclusive, so a premium ends the day before the old policy
     * expires (the new premium owns that day), unless its due date on that day is already paid: then it ends on the
     * expiry and the payment keeps its due date. With no expiry it ends today. An earlier end is never moved later.
     *
     * @param  InsurancePolicy  $new  the policy that takes over
     * @param  InsurancePolicy  $old  the policy being replaced
     */
    private function movePremiums(InsurancePolicy $new, InsurancePolicy $old): void
    {
        $type = $new->getMorphClass();
        $newHasOwn = PlannedExpense::query()->where('card_type', $type)->where('card_id', $new->id)->exists();

        foreach (PlannedExpense::query()->where('card_type', $type)->where('card_id', $old->id)->get() as $premium) {
            $premium->forceFill($newHasOwn ? ['end_date' => $this->endFor($premium, $old)] : ['card_type' => $type, 'card_id' => $new->id])->save();
        }
    }

    /**
     * The last day of an old premium that the new policy's own premium replaces.
     *
     * @param  PlannedExpense  $premium  the old premium
     * @param  InsurancePolicy  $old  the policy being replaced
     */
    private function endFor(PlannedExpense $premium, InsurancePolicy $old): string
    {
        $expiry = $old->expiry_date;

        if ($expiry === null) {
            $end = CarbonImmutable::today('Europe/Rome');
        } else {
            $day = CarbonImmutable::parse($expiry->toDateString(), 'Europe/Rome');
            $onExpiry = $this->deadlines->between([$premium], $day, $day);
            $end = $onExpiry !== [] && $onExpiry[0]->isPaid() ? $day : $day->subDay();
        }

        $current = $premium->end_date;

        return ($current !== null && $current->toDateString() < $end->toDateString() ? $current : $end)->toDateString();
    }
}
