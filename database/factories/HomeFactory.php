<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatoCasa;
use App\Models\Family;
use App\Models\Home;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Home>
 */
final class HomeFactory extends Factory
{
    /**
     * Una casa con un indirizzo italiano.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'name' => 'Casa di '.fake()->streetName(),
            'status' => StatoCasa::Active,
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'Italia',
            'is_owned' => fake()->boolean(),
        ];
    }
}
