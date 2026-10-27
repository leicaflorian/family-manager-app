<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UtilityStatus;
use App\Enums\UtilityType;
use App\Models\Family;
use App\Models\Utility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Utility>
 */
final class UtilityFactory extends Factory
{
    /**
     * An electricity utility.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'type' => UtilityType::Electricity,
            'status' => UtilityStatus::Active,
            'customer_code' => fake()->numerify('########'),
        ];
    }
}
