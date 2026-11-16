<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\InsurancePolicy;
use LogicException;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The policy *I paid it* can renew with its premium: the choice *Only the payment / Same company, renewed / I changed
 * company* of the panel.
 */
#[TypeScript]
final class PolicyRenewalData extends Data
{
    /**
     * @param  int  $policy_id  the id of the policy
     * @param  string  $policy  the title, then the number and the insurer when filled, joined with « · »
     * @param  string  $expiry_date  the expiry of the policy now, as `Y-m-d`: sent back so a renewal is not made twice
     * @param  string  $proposed_expiry  the new expiry proposed with the same company, a year later, as `Y-m-d`
     */
    public function __construct(
        public readonly int $policy_id,
        public readonly string $policy,
        public readonly string $expiry_date,
        public readonly string $proposed_expiry,
    ) {}

    /**
     * Builds the choice from a policy that has an expiry.
     *
     * @param  InsurancePolicy  $policy  the policy, with its expiry
     *
     * @throws LogicException if the policy has no expiry
     */
    public static function fromPolicy(InsurancePolicy $policy): self
    {
        $expiry = $policy->expiry_date ?? throw new LogicException('A policy without an expiry cannot be renewed.');

        return new self(
            policy_id: $policy->id,
            policy: ReplaceablePolicyData::fromPolicy($policy)->label,
            expiry_date: $expiry->toDateString(),
            proposed_expiry: $expiry->addYearNoOverflow()->toDateString(),
        );
    }
}
