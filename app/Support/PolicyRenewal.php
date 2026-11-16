<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\InsurancePolicyStatus;
use App\Models\InsurancePolicy;
use App\Models\PlannedExpense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

/**
 * Whether *I paid it* of a due date also offers to renew a policy: the premium of a policy near its expiry is the
 * moment the person knows whether the company stayed the same or changed.
 */
final class PolicyRenewal
{
    /** How many days before its expiry a policy can be renewed with its premium. */
    public const int DAYS_BEFORE = 30;

    /**
     * The policy a due date can renew: the planned expense is the premium of a policy, the policy is active or
     * expired, its expiry is at most 30 days away (or passed), and the person who is signed in can change it and
     * create a new one. Otherwise null. A paid due date offers nothing: *I paid it* is over.
     *
     * @param  Deadline  $deadline  the due date
     * @param  CarbonImmutable  $today  today in Rome
     */
    public static function policyFor(Deadline $deadline, CarbonImmutable $today): ?InsurancePolicy
    {
        $planned = $deadline->planned;

        if (! $planned instanceof PlannedExpense || $planned->card_type !== 'insurance_policy' || $deadline->isPaid()) {
            return null;
        }

        $policy = $planned->card;

        if (! $policy instanceof InsurancePolicy
            || ! in_array($policy->status, [InsurancePolicyStatus::Active, InsurancePolicyStatus::Expired], true)
            || $policy->expiry_date === null
            || $policy->expiry_date->toDateString() > $today->addDays(self::DAYS_BEFORE)->toDateString()) {
            return null;
        }

        $user = Auth::user();

        return $user instanceof User && $user->can('update', $policy) && $user->can('create', InsurancePolicy::class) ? $policy : null;
    }
}
