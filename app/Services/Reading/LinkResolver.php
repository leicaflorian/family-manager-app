<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Data\Reading\Extraction;
use App\Models\InsurancePolicy;
use App\Models\Utility;
use App\Support\Reading\Choices;

/**
 * Finds what the identifiers on a paper point to (`resolveLinks` of the old reader). It searches the current
 * family: the caller wraps it in `CurrentFamily::using()` when the family is the one of a file.
 */
final readonly class LinkResolver
{
    /**
     * @param  Matcher  $matcher  the exact searches of the cards
     */
    public function __construct(private Matcher $matcher) {}

    /**
     * The links of a reading.
     *
     * @param  Extraction  $x  what the model read
     * @param  ReadingContext  $context  the lists the model chose from
     */
    public function resolve(Extraction $x, ReadingContext $context): ResolvedLinks
    {
        $utility = $this->matcher->utility($x->supply_point_code, $x->customer_code);
        $vehicle = $this->matcher->vehicle($x->plate_number);
        $policy = $this->matcher->policy($x->policy_number);

        // A policy of a car tells the vehicle even without the plate.
        $vehicleId = $vehicle->id ?? $policy?->vehicle_id;
        $organizationId = $utility->organization_id ?? $policy->organization_id ?? Choices::pick($context->organizations, $x->organization_id)
            ?? $this->matcher->organizationByName($x->issuer_name)?->id;
        $personId = $this->matcher->personByTaxCode($x->tax_code)->id ?? Choices::pick($context->people, $x->person_id)
            ?? $utility->person_id ?? $policy?->person_id;

        $subscription = ! $utility instanceof Utility && ! $policy instanceof InsurancePolicy && $vehicleId === null
            ? $this->matcher->subscription($organizationId)
            : null;

        return new ResolvedLinks(
            utility: $utility,
            vehicle: $vehicle,
            vehicleId: $vehicleId,
            policy: $policy,
            subscription: $subscription,
            personId: $personId,
            organizationId: $organizationId,
            issuer: Choices::label($context->organizations, $organizationId) ?? $x->issuer_name,
        );
    }
}
