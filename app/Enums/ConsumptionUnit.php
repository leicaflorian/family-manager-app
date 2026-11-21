<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The unit a consumption is measured in (`utility_consumptions.unit`).
 */
enum ConsumptionUnit: string
{
    use HasLabel;

    case Kwh = 'kwh';
    case Smc = 'smc';
    case M3 = 'm3';

    /**
     * The unit of a type of utility: electricity in kWh, gas in Smc, water in m3, the rest has none.
     *
     * @param  UtilityType  $type  the type of the utility
     */
    public static function forUtility(UtilityType $type): ?self
    {
        return match ($type) {
            UtilityType::Electricity => self::Kwh,
            UtilityType::Gas => self::Smc,
            UtilityType::Water => self::M3,
            default => null,
        };
    }
}
