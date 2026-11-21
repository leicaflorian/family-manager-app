<?php

declare(strict_types=1);

namespace App\Data\Consumption;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The four figures of the Consumption page, over the months shown.
 */
#[TypeScript]
final class ConsumptionFiguresData extends Data
{
    /**
     * @param  float  $quantity  the consumption of the months
     * @param  float|null  $previousQuantity  the consumption of the 12 months before, null when either period has
     *                                        fewer than 330 covered days
     * @param  int  $coveredMonths  the months with data
     * @param  float  $amount  the euro of the months
     * @param  int  $bills  the bills that touch the months
     * @param  int  $toPay  how many of them are still to pay
     * @param  float|null  $averagePrice  euro per unit, everything included; null without consumption
     * @param  string|null  $dearestMonth  the complete month with the highest price, `YYYY-MM`
     * @param  float|null  $dearestPrice  its price
     */
    public function __construct(
        public readonly float $quantity,
        public readonly ?float $previousQuantity,
        public readonly int $coveredMonths,
        public readonly float $amount,
        public readonly int $bills,
        public readonly int $toPay,
        public readonly ?float $averagePrice,
        public readonly ?string $dearestMonth,
        public readonly ?float $dearestPrice,
    ) {}
}
