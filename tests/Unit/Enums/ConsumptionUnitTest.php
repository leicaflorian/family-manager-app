<?php

declare(strict_types=1);

use App\Enums\ConsumptionUnit;
use App\Enums\UtilityType;

test('the unit follows the type of the utility', function (UtilityType $type, ?ConsumptionUnit $unit): void {
    expect(ConsumptionUnit::forUtility($type))->toBe($unit);
})->with([
    'electricity' => [UtilityType::Electricity, ConsumptionUnit::Kwh],
    'gas' => [UtilityType::Gas, ConsumptionUnit::Smc],
    'water' => [UtilityType::Water, ConsumptionUnit::M3],
    'internet' => [UtilityType::Internet, null],
]);
