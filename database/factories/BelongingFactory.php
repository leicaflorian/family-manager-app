<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BelongingStatus;
use App\Models\Belonging;
use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Belonging>
 */
final class BelongingFactory extends Factory
{
    /**
     * A purchased belonging, with its warranty.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'name' => fake()->randomElement(['TV Samsung', 'Lavatrice Bosch', 'Divano']),
            'status' => BelongingStatus::InUse,
            'purchase_date' => fake()->dateTimeBetween('-3 years'),
            'purchase_price' => fake()->randomFloat(2, 100, 1500),
        ];
    }
}
