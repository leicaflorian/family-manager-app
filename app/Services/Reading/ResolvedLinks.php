<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Models\InsurancePolicy;
use App\Models\Subscription;
use App\Models\Utility;
use App\Models\Vehicle;

/**
 * The cards, the person and the issuer a paper points to, found once and shared by the document, the movement and
 * the event of a reading.
 */
final readonly class ResolvedLinks
{
    /**
     * @param  Utility|null  $utility  the active utility with the code on the paper
     * @param  Vehicle|null  $vehicle  the vehicle with the plate on the paper
     * @param  int|null  $vehicleId  the vehicle, or the one of the policy when the plate is missing
     * @param  InsurancePolicy|null  $policy  the policy with the number on the paper
     * @param  Subscription|null  $subscription  the only active subscription of the issuer, when no other card fits
     * @param  int|null  $personId  the person the paper is about: by tax code, otherwise the model's choice
     * @param  int|null  $organizationId  the issuer: from the utility or the policy, otherwise the model's choice or its name
     * @param  string|null  $issuer  the name of the issuer, from the list or as printed
     */
    public function __construct(
        public ?Utility $utility,
        public ?Vehicle $vehicle,
        public ?int $vehicleId,
        public ?InsurancePolicy $policy,
        public ?Subscription $subscription,
        public ?int $personId,
        public ?int $organizationId,
        public ?string $issuer,
    ) {}
}
