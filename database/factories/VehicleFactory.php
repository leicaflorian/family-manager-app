<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatoVeicolo;
use App\Models\Family;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
final class VehicleFactory extends Factory
{
    /**
     * Un'auto con targa italiana.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'name' => fake()->randomElement(['Fiat Panda', 'Renault Clio', 'Toyota Yaris']),
            'status' => StatoVeicolo::Active,
            'plate_number' => strtoupper(fake()->bothify('??###??')),
            'registration_year' => fake()->numberBetween(2010, 2026),
            'is_owned' => true,
        ];
    }
}
