<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConsumptionSource;
use App\Enums\ConsumptionUnit;
use App\Models\Family;
use App\Models\Utility;
use App\Models\UtilityConsumption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UtilityConsumption>
 */
final class UtilityConsumptionFactory extends Factory
{
    /**
     * A month of an electricity utility: 200 kWh for 70 euro.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'utility_id' => fn (array $attributes) => Utility::factory()->state(['family_id' => $attributes['family_id']]),
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'quantity' => 200,
            'unit' => ConsumptionUnit::Kwh,
            'amount' => 70,
            'source' => ConsumptionSource::Reading,
        ];
    }
}
