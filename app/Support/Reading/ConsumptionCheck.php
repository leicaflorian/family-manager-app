<?php

declare(strict_types=1);

namespace App\Support\Reading;

use App\Enums\ConsumptionOutcome;
use App\Enums\ConsumptionUnit;
use App\Enums\UtilityType;
use Carbon\CarbonImmutable;

/**
 * Decides whether the consumption read from a bill is worth saving, and whether a price per unit looks wrong.
 * Pure: it reads nothing and writes nothing.
 */
final class ConsumptionCheck
{
    /** The longest period of a bill, in days with both ends counted. */
    private const int MAX_PERIOD_DAYS = 400;

    /**
     * The highest quantity of a bill for each unit: more is a misreading.
     *
     * @var array<string, float>
     */
    private const array CAPS = ['kwh' => 20000.0, 'smc' => 5000.0, 'm3' => 1000.0];

    /**
     * The usual price per unit, as lowest and highest euro.
     *
     * @var array<string, array{float, float}>
     */
    private const array PRICES = ['kwh' => [0.05, 1.50], 'smc' => [0.30, 5.00], 'm3' => [0.50, 15.00]];

    /**
     * What comes of the consumption a reading found.
     *
     * @param  UtilityType  $type  the type of the utility the bill belongs to
     * @param  float|null  $quantity  the consumption as read
     * @param  string|null  $unit  the unit as read (`kwh`, `smc`, `m3`)
     * @param  string|null  $start  the first day of the period, `YYYY-MM-DD`
     * @param  string|null  $end  the last day of the period, `YYYY-MM-DD`
     * @return ConsumptionOutcome `Saved` when it is worth keeping, otherwise the reason it is not
     */
    public static function outcome(UtilityType $type, ?float $quantity, ?string $unit, ?string $start, ?string $end): ConsumptionOutcome
    {
        if ($quantity === null) {
            return ConsumptionOutcome::NoConsumption;
        }

        $expected = ConsumptionUnit::forUtility($type);

        if (! $expected instanceof ConsumptionUnit || self::normalize($unit) !== $expected->value) {
            return ConsumptionOutcome::WrongUnit;
        }

        if (! is_finite($quantity) || $quantity <= 0 || $quantity > self::CAPS[$expected->value]) {
            return ConsumptionOutcome::OutOfRange;
        }

        $first = ValidDate::carbon($start);
        $last = ValidDate::carbon($end);

        if (! $first instanceof CarbonImmutable || ! $last instanceof CarbonImmutable || $first->greaterThan($last) || $first->diffInDays($last) + 1 > self::MAX_PERIOD_DAYS) {
            return ConsumptionOutcome::BadPeriod;
        }

        return ConsumptionOutcome::Saved;
    }

    /**
     * Whether the price per unit of a bill is out of the usual range, so it is worth a second look.
     *
     * @param  ConsumptionUnit  $unit  the unit of the consumption
     * @param  float  $amount  the total of the bill, in euro
     * @param  float  $quantity  the consumption, in the unit
     * @return bool false when the quantity is zero or negative, because there is no price to compare
     */
    public static function priceOutOfRange(ConsumptionUnit $unit, float $amount, float $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        [$low, $high] = self::PRICES[$unit->value];
        $price = $amount / $quantity;

        return $price < $low || $price > $high;
    }

    /**
     * The unit as the model wrote it, lowercase, without spaces and with `m³` as `m3`.
     *
     * @param  string|null  $unit  the unit as read
     */
    private static function normalize(?string $unit): ?string
    {
        return $unit === null ? null : str_replace('³', '3', mb_strtolower(trim($unit)));
    }
}
