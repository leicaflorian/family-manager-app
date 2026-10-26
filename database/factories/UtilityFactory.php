<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatoUtenza;
use App\Enums\TipoUtenza;
use App\Models\Family;
use App\Models\Utility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Utility>
 */
final class UtilityFactory extends Factory
{
    /**
     * Un'utenza della luce.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'type' => TipoUtenza::Electricity,
            'status' => StatoUtenza::Active,
            'customer_code' => fake()->numerify('########'),
        ];
    }
}
