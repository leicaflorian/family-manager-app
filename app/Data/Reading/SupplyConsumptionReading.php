<?php

declare(strict_types=1);

namespace App\Data\Reading;

/**
 * One supply of a bill that carries more than one (electricity and gas on the same paper), as the short reading of the
 * consumption found it: its type, its code and its own consumption, period and amount.
 */
final readonly class SupplyConsumptionReading
{
    /**
     * @param  string|null  $utilityType  the type of the supply, a value of `UtilityType` (`electricity`, `gas`, `water`)
     * @param  string|null  $supplyPointCode  the code of the supply as printed (POD, PDR)
     * @param  ConsumptionReading  $reading  the consumption of this supply, with the amount of the supply as its total
     */
    public function __construct(
        public ?string $utilityType,
        public ?string $supplyPointCode,
        public ConsumptionReading $reading,
    ) {}
}
