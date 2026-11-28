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
     * The links of a reading. With more than one supply each one looks for its utility, and the first utility found
     * tells the issuer, the person and whether a subscription is searched, as the only utility does.
     *
     * @param  Extraction  $x  what the model read
     * @param  ReadingContext  $context  the lists the model chose from
     */
    public function resolve(Extraction $x, ReadingContext $context): ResolvedLinks
    {
        if (! $x->isMultiSupply()) {
            return $this->withUtility($x, $context, $this->matcher->utility($x->supply_point_code, $x->customer_code), []);
        }

        $utilities = array_values(array_filter($this->matcher->supplyUtilities($x->supplies, $x->customer_code)));

        return $this->withUtility($x, $context, $utilities[0] ?? null, $utilities);
    }

    /**
     * The links of one supply of a bill with more than one, with the utility already found for it (or none).
     *
     * @param  Extraction  $supplyView  the reading seen from the supply (`Extraction::forSupply()`)
     * @param  ReadingContext  $context  the lists the model chose from
     * @param  Utility|null  $utility  the utility of the supply
     */
    public function forSupply(Extraction $supplyView, ReadingContext $context, ?Utility $utility): ResolvedLinks
    {
        return $this->withUtility($supplyView, $context, $utility, $utility instanceof Utility ? [$utility] : []);
    }

    /**
     * The links of a reading whose utility is known.
     *
     * @param  Extraction  $x  what the model read
     * @param  ReadingContext  $context  the lists the model chose from
     * @param  Utility|null  $utility  the utility of the paper
     * @param  list<Utility>  $utilities  the utilities of the supplies, when the paper has more than one
     */
    private function withUtility(Extraction $x, ReadingContext $context, ?Utility $utility, array $utilities): ResolvedLinks
    {
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
            utilities: $utilities,
        );
    }
}
