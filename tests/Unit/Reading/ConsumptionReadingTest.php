<?php

declare(strict_types=1);

use App\Data\Reading\ConsumptionReading;
use App\Data\Reading\SupplyConsumptionReading;
use App\Enums\UtilityType;
use App\Models\Utility;

/**
 * The short reading of the Enel bill of electricity and gas of the Bianchi.
 *
 * @param  string|null  $electricityCode  the code printed for the electricity
 * @param  string|null  $gasCode  the code printed for the gas
 */
function consumptionReadingEnel(?string $electricityCode = 'IT 001 E 87654321', ?string $gasCode = '00881234567890'): ConsumptionReading
{
    $electricity = new ConsumptionReading(isBill: true, periodStart: '2026-09-01', periodEnd: '2026-09-30', quantity: 201.0, unit: 'kwh', totalAmount: 96.25);
    $gas = new ConsumptionReading(isBill: true, periodStart: '2026-10-01', periodEnd: '2026-10-31', quantity: 48.0, unit: 'smc', totalAmount: 69.40);

    return new ConsumptionReading(
        isBill: true,
        periodStart: '2026-09-01',
        periodEnd: '2026-09-30',
        quantity: 201.0,
        unit: 'kwh',
        totalAmount: 165.65,
        supplies: [
            new SupplyConsumptionReading('electricity', $electricityCode, $electricity),
            new SupplyConsumptionReading('gas', $gasCode, $gas),
        ],
    );
}

/** A utility not saved, of a type and with a supply code. */
function consumptionReadingUtility(UtilityType $type, ?string $code): Utility
{
    return new Utility(['type' => $type, 'supply_point_code' => $code]);
}

test('a reading without supplies gives itself for any utility', function (): void {
    $reading = new ConsumptionReading(isBill: true, quantity: 120.0, unit: 'smc');

    expect($reading->supplies)->toBe([])
        ->and($reading->forUtility(consumptionReadingUtility(UtilityType::Gas, '00881234567890')))->toBe($reading);
});

test('the supply with the code of the utility, written another way', function (): void {
    $reading = consumptionReadingEnel();

    expect($reading->forUtility(consumptionReadingUtility(UtilityType::Gas, '0088 1234 5678 90'))->quantity)->toBe(48.0)
        ->and($reading->forUtility(consumptionReadingUtility(UtilityType::Electricity, 'IT001E87654321'))->totalAmount)->toBe(96.25);
});

test('without the code, the only supply of the type of the utility', function (): void {
    $reading = consumptionReadingEnel(gasCode: null);

    expect($reading->forUtility(consumptionReadingUtility(UtilityType::Gas, null))->quantity)->toBe(48.0);
});

test('without a supply of its code or its type, the flat fields', function (): void {
    $reading = consumptionReadingEnel();

    expect($reading->forUtility(consumptionReadingUtility(UtilityType::Water, null)))->toBe($reading)
        ->and($reading->forUtility(consumptionReadingUtility(UtilityType::Gas, '99999999999999'))->quantity)->toBe(48.0);
});
