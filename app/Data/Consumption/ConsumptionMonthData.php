<?php

declare(strict_types=1);

namespace App\Data\Consumption;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One month of the Consumption page: its share of the bills that cover it, with the same month of the year before.
 */
#[TypeScript]
final class ConsumptionMonthData extends Data
{
    /**
     * @param  string  $month  the month, `YYYY-MM`
     * @param  float|null  $quantity  the consumption of the month, in the unit of the utility; null without bills
     * @param  float|null  $amount  the euro of the month; null when no bill of the month has an amount
     * @param  float|null  $price  euro per unit, null when the consumption is zero or less or there is no amount
     * @param  float|null  $previousQuantity  the consumption of the same month of the year before, null without bills
     * @param  float|null  $previousAmount  the euro of the same month of the year before, null without an amount
     * @param  string  $coverage  `complete` when a bill covers every day, `partial` when some, `none` when none
     * @param  int|null  $coveredUntil  the last covered day when the end of the month is not covered («up to the 14th»)
     * @param  bool  $estimated  whether one of its bills has estimated consumption
     * @param  bool  $adjusted  whether one of its bills has adjustments
     * @param  list<int>  $billIds  the ids of the bills that cover it
     */
    public function __construct(
        public readonly string $month,
        public readonly ?float $quantity,
        public readonly ?float $amount,
        public readonly ?float $price,
        public readonly ?float $previousQuantity,
        public readonly ?float $previousAmount,
        public readonly string $coverage,
        public readonly ?int $coveredUntil,
        public readonly bool $estimated,
        public readonly bool $adjusted,
        public readonly array $billIds,
    ) {}
}
