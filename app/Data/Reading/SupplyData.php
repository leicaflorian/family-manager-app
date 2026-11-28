<?php

declare(strict_types=1);

namespace App\Data\Reading;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One supply of a bill with more than one (a dual-fuel bill: electricity and gas on the same paper), as the model
 * reads it: its type, its code, what it costs and its consumption. Every field can be empty.
 */
#[TypeScript]
final class SupplyData extends Data
{
    /**
     * @param  string|null  $utility_type  a value of `UtilityType`
     * @param  string|null  $supply_point_code  POD or PDR
     * @param  float|null  $amount  the total of this supply in euro
     * @param  string|null  $period_start  `YYYY-MM-DD`, the first day of the consumption billed
     * @param  string|null  $period_end  `YYYY-MM-DD`, the last day of the consumption billed
     * @param  float|null  $consumption_quantity  what the bill charges for the period: kWh, Smc or m3
     * @param  string|null  $consumption_unit  a value of `ConsumptionUnit`
     * @param  bool|null  $consumption_estimated  whether the consumption is estimated or on account
     * @param  bool|null  $consumption_adjusted  whether the bill recalculates periods already billed
     * @param  float|null  $consumption_energy_amount  euro for the sale of electricity or gas alone
     * @param  float|null  $consumption_f1  kWh of the first band, electricity only
     * @param  float|null  $consumption_f2  kWh of the second band
     * @param  float|null  $consumption_f3  kWh of the third band
     */
    public function __construct(
        public ?string $utility_type = null,
        public ?string $supply_point_code = null,
        public ?float $amount = null,
        public ?string $period_start = null,
        public ?string $period_end = null,
        public ?float $consumption_quantity = null,
        public ?string $consumption_unit = null,
        public ?bool $consumption_estimated = null,
        public ?bool $consumption_adjusted = null,
        public ?float $consumption_energy_amount = null,
        public ?float $consumption_f1 = null,
        public ?float $consumption_f2 = null,
        public ?float $consumption_f3 = null,
    ) {}

    /** Whether the supply has neither a code nor an amount: nothing to tell it apart, so it is dropped. */
    public function isEmpty(): bool
    {
        return trim($this->supply_point_code ?? '') === '' && $this->amount === null;
    }
}
