<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\InsurancePolicy;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An old policy a new one can replace, as a choice of the card form.
 */
#[TypeScript]
final class ReplaceablePolicyData extends Data
{
    /**
     * @param  int  $id  the id
     * @param  string  $label  the title, then the number and the insurer when filled, joined with « · »
     * @param  string|null  $expiry_date  the expiry, as `Y-m-d`
     * @param  string  $type  the type of the policy
     * @param  int|null  $vehicle_id  the vehicle it covers
     * @param  int|null  $home_id  the home it covers
     * @param  int|null  $employment_id  the employment it covers
     */
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly ?string $expiry_date,
        public readonly string $type,
        public readonly ?int $vehicle_id,
        public readonly ?int $home_id,
        public readonly ?int $employment_id,
    ) {}

    /**
     * Builds the choice from a policy.
     *
     * @param  InsurancePolicy  $policy  the policy
     */
    public static function fromPolicy(InsurancePolicy $policy): self
    {
        $parts = array_filter(
            [$policy->title, $policy->policy_number, $policy->organization?->name],
            static fn (?string $part): bool => $part !== null && $part !== '',
        );

        return new self(
            id: $policy->id,
            label: implode(' · ', $parts),
            expiry_date: $policy->expiry_date?->format('Y-m-d'),
            type: $policy->type->value,
            vehicle_id: $policy->vehicle_id,
            home_id: $policy->home_id,
            employment_id: $policy->employment_id,
        );
    }
}
