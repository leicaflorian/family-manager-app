<?php

declare(strict_types=1);

use App\Enums\ConsumptionOutcome;
use App\Enums\ConsumptionUnit;
use App\Enums\UtilityType;
use App\Support\Reading\ConsumptionCheck;

it('accepts the gas example of the analysis', function (): void {
    expect(ConsumptionCheck::outcome(UtilityType::Gas, 86.0, 'smc', '2026-08-01', '2026-09-30'))->toBe(ConsumptionOutcome::Saved);
});

it('says there is no consumption when the quantity is missing', function (): void {
    expect(ConsumptionCheck::outcome(UtilityType::Gas, null, 'smc', '2026-08-01', '2026-09-30'))->toBe(ConsumptionOutcome::NoConsumption);
});

it('rejects a unit that is not the one of the utility', function (): void {
    expect(ConsumptionCheck::outcome(UtilityType::Gas, 86.0, 'kwh', '2026-08-01', '2026-09-30'))->toBe(ConsumptionOutcome::WrongUnit)
        ->and(ConsumptionCheck::outcome(UtilityType::Gas, 86.0, null, '2026-08-01', '2026-09-30'))->toBe(ConsumptionOutcome::WrongUnit)
        ->and(ConsumptionCheck::outcome(UtilityType::Internet, 86.0, 'kwh', '2026-08-01', '2026-09-30'))->toBe(ConsumptionOutcome::WrongUnit);
});

it('compares the unit ignoring case and spaces', function (): void {
    expect(ConsumptionCheck::outcome(UtilityType::Electricity, 300.0, ' kWh ', '2026-08-01', '2026-09-30'))->toBe(ConsumptionOutcome::Saved);
});

it('rejects quantities out of range', function (float $quantity, UtilityType $type, string $unit): void {
    expect(ConsumptionCheck::outcome($type, $quantity, $unit, '2026-08-01', '2026-09-30'))->toBe(ConsumptionOutcome::OutOfRange);
})->with([
    'just above the electricity cap' => [20001.0, UtilityType::Electricity, 'kwh'],
    'infinite' => [INF, UtilityType::Electricity, 'kwh'],
    'not a number' => [NAN, UtilityType::Electricity, 'kwh'],
    'zero' => [0.0, UtilityType::Gas, 'smc'],
    'negative' => [-3.0, UtilityType::Gas, 'smc'],
    'above the gas cap' => [5000.5, UtilityType::Gas, 'smc'],
    'above the water cap' => [1000.5, UtilityType::Water, 'm3'],
]);

it('accepts the caps themselves', function (): void {
    expect(ConsumptionCheck::outcome(UtilityType::Electricity, 20000.0, 'kwh', '2026-01-01', '2026-02-01'))->toBe(ConsumptionOutcome::Saved)
        ->and(ConsumptionCheck::outcome(UtilityType::Water, 1000.0, 'm3', '2026-01-01', '2026-02-01'))->toBe(ConsumptionOutcome::Saved);
});

it('rejects a bad period', function (?string $start, ?string $end): void {
    expect(ConsumptionCheck::outcome(UtilityType::Gas, 86.0, 'smc', $start, $end))->toBe(ConsumptionOutcome::BadPeriod);
})->with([
    'end before start' => ['2026-09-30', '2026-08-01'],
    '401 days' => ['2026-01-01', '2027-02-05'],
    'missing start' => [null, '2026-09-30'],
    'missing end' => ['2026-08-01', null],
    'not a real day' => ['2026-02-30', '2026-09-30'],
]);

it('accepts 400 days and a single day', function (): void {
    expect(ConsumptionCheck::outcome(UtilityType::Gas, 86.0, 'smc', '2026-01-01', '2027-02-04'))->toBe(ConsumptionOutcome::Saved)
        ->and(ConsumptionCheck::outcome(UtilityType::Gas, 86.0, 'smc', '2026-01-01', '2026-01-01'))->toBe(ConsumptionOutcome::Saved);
});

it('flags a price out of the range of the unit', function (ConsumptionUnit $unit, float $amount, float $quantity, bool $out): void {
    expect(ConsumptionCheck::priceOutOfRange($unit, $amount, $quantity))->toBe($out);
})->with([
    'gas at 0.08' => [ConsumptionUnit::Smc, 98.40, 1240.0, true],
    'gas at 1.20' => [ConsumptionUnit::Smc, 103.2, 86.0, false],
    'kwh at 0.31' => [ConsumptionUnit::Kwh, 84.0, 271.0, false],
    'kwh too dear' => [ConsumptionUnit::Kwh, 200.0, 100.0, true],
    'water too cheap' => [ConsumptionUnit::M3, 10.0, 100.0, true],
    'water fine' => [ConsumptionUnit::M3, 150.0, 100.0, false],
    'no quantity' => [ConsumptionUnit::Kwh, 10.0, 0.0, false],
    'negative quantity' => [ConsumptionUnit::Kwh, 10.0, -5.0, false],
]);
