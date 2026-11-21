<?php

declare(strict_types=1);

namespace App\Data\Reading;

/**
 * What the short reading of a bill already in the archive found: whether it is a bill of electricity, gas or water,
 * and its consumption, period and total, each null when the paper does not say.
 */
final readonly class ConsumptionReading
{
    /**
     * @param  bool  $isBill  whether the paper is a utility bill of electricity, gas or water
     * @param  string|null  $periodStart  the first day of the consumption billed, `YYYY-MM-DD` as the model wrote it
     * @param  string|null  $periodEnd  the last day of the consumption billed, `YYYY-MM-DD` as the model wrote it
     * @param  float|null  $quantity  the consumption billed for the period
     * @param  string|null  $unit  the unit as the model wrote it (`kwh`, `smc`, `m3`)
     * @param  float|null  $totalAmount  the total to pay, in euro
     * @param  bool|null  $estimated  whether the consumption is estimated or on account
     * @param  bool|null  $adjusted  whether the bill recalculates periods already billed
     * @param  float|null  $energyAmount  euro for the sale of electricity or gas alone (consumption and fixed part of the sale); null for water
     * @param  float|null  $f1  kWh of the first band, electricity only
     * @param  float|null  $f2  kWh of the second band
     * @param  float|null  $f3  kWh of the third band
     */
    public function __construct(
        public bool $isBill,
        public ?string $periodStart = null,
        public ?string $periodEnd = null,
        public ?float $quantity = null,
        public ?string $unit = null,
        public ?float $totalAmount = null,
        public ?bool $estimated = null,
        public ?bool $adjusted = null,
        public ?float $energyAmount = null,
        public ?float $f1 = null,
        public ?float $f2 = null,
        public ?float $f3 = null,
    ) {}
}
