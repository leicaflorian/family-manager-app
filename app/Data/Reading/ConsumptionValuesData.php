<?php

declare(strict_types=1);

namespace App\Data\Reading;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The consumption a read utility bill proposes to record, with the corrections of the person.
 */
#[TypeScript]
final class ConsumptionValuesData extends Data
{
    /**
     * @param  string|null  $period_start  the first day of the period, `YYYY-MM-DD`
     * @param  string|null  $period_end  the last day of the period, `YYYY-MM-DD`
     * @param  float|null  $quantity  the consumption, in the unit
     * @param  string  $unit  a value of `ConsumptionUnit`: the one of the type of the utility, not the one read
     * @param  float|null  $amount  the total of the bill: the amount of the movement as corrected, otherwise the one read
     * @param  bool  $is_estimated  whether the consumption is estimated or on account
     */
    public function __construct(
        public readonly ?string $period_start,
        public readonly ?string $period_end,
        public readonly ?float $quantity,
        public readonly string $unit,
        public readonly ?float $amount,
        public readonly bool $is_estimated,
    ) {}
}
