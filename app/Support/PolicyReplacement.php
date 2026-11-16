<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Expense;
use App\Models\InsurancePolicy;

/**
 * The way into the form of a new policy that replaces an old one: the address of the form with the data of the old
 * policy already in it.
 */
final class PolicyReplacement
{
    /**
     * The address of the new policy form, prefilled from the old policy and marked as its replacement.
     *
     * @param  InsurancePolicy  $old  the policy being replaced
     * @param  Expense|null  $payment  the payment just made, which the new policy takes
     */
    public static function createUrl(InsurancePolicy $old, ?Expense $payment = null): string
    {
        $query = array_filter([
            'card' => 'insurance_policy',
            'title' => $old->title,
            'type' => $old->type->value,
            'vehicle_id' => $old->vehicle_id,
            'home_id' => $old->home_id,
            'employment_id' => $old->employment_id,
            'person_id' => $old->person_id,
            'start_date' => $old->expiry_date?->format('Y-m-d'),
            'replaces' => $old->id,
            'payment' => $payment?->id,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return route('cards.create', $query);
    }
}
