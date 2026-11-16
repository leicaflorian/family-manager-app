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

    /** What *I paid it* can do with the policy: `only` the payment, the `same` company renewed, or a `changed` company. */
    public const array CHOICES = ['only', 'same', 'changed'];

    /**
     * The policy a due date can renew: the premium's policy (see `linkedPolicy()`), active or expired, with an expiry
     * at most 30 days away (or passed). Otherwise null. A paid due date offers nothing: *I paid it* is over.
     *
     * @param  Deadline  $deadline  the due date
     * @param  CarbonImmutable  $today  today in Rome
     */
    public static function policyFor(Deadline $deadline, CarbonImmutable $today): ?InsurancePolicy
    {
        $policy = $deadline->isPaid() ? null : self::linkedPolicy($deadline);

        if (! $policy instanceof InsurancePolicy
            || ! in_array($policy->status, [InsurancePolicyStatus::Active, InsurancePolicyStatus::Expired], true)
            || $policy->expiry_date === null
            || $policy->expiry_date->toDateString() > $today->addDays(self::DAYS_BEFORE)->toDateString()) {
            return null;
        }

        return $policy;
    }

    /**
     * The policy whose premium the due date is, when the person who is signed in can change it and create a new one,
     * whatever its status and expiry. Otherwise null.
     *
     * @param  Deadline  $deadline  the due date
     */
    public static function linkedPolicy(Deadline $deadline): ?InsurancePolicy
    {
        $planned = $deadline->planned;

        if (! $planned instanceof PlannedExpense || $planned->card_type !== 'insurance_policy') {
            return null;
        }

        $policy = $planned->card;
        $user = Auth::user();

        return $policy instanceof InsurancePolicy && $user instanceof User && $user->can('update', $policy) && $user->can('create', InsurancePolicy::class)
            ? $policy
            : null;
    }
}
