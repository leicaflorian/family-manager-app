<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Family>
 */
final class FamilyFactory extends Factory
{
    /**
     * A family with an Italian last name.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->lastName(),
            'welcomed_at' => now(),
        ];
    }

    /**
     * A family just registered, which has not done the *Welcome* yet.
     */
    public function daAccogliere(): self
    {
        return $this->state(['welcomed_at' => null]);
    }
}
