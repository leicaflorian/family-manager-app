<?php

declare(strict_types=1);

namespace App\Actions\Consumption;

use Carbon\CarbonImmutable;

/**
 * The figures of one consumption row, before they are saved by `SaveConsumption`.
 */
final readonly class ConsumptionValues
{
    /**
     * @param  CarbonImmutable  $periodStart  the first day of the period
     * @param  CarbonImmutable  $periodEnd  the last day of the period
     * @param  float  $quantity  the consumption, in the unit of the utility
     * @param  float|null  $amount  the total of the bill
     * @param  bool  $isEstimated  whether the bill is based on an estimate
     * @param  bool  $hasAdjustments  whether the bill carries an adjustment
     * @param  float|null  $energyAmount  the energy part of the bill
     * @param  float|null  $f1  the quantity in band F1
     * @param  float|null  $f2  the quantity in band F2
     * @param  float|null  $f3  the quantity in band F3
     */
    public function __construct(
        public CarbonImmutable $periodStart,
        public CarbonImmutable $periodEnd,
        public float $quantity,
        public ?float $amount,
        public bool $isEstimated,
        public bool $hasAdjustments = false,
        public ?float $energyAmount = null,
        public ?float $f1 = null,
        public ?float $f2 = null,
        public ?float $f3 = null,
    ) {}
}
