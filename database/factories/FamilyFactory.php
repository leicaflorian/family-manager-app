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
     * A family with an Italian last name, never "Bianchi": the seeded family of that name is looked up
     * by name in the tests (`->where('name', 'Bianchi')->sole()`), and a second one made it throw.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->valid(fn (string $name): bool => $name !== 'Bianchi')->lastName(),
            'welcomed_at' => now(),
            'budget_enabled' => true,
            'mail_enabled' => true,
        ];
    }

    /**
     * A family with the Budget and the mail off, as a new one starts.
     */
    public function withoutFeatures(): static
    {
        return $this->state(['budget_enabled' => false, 'mail_enabled' => false]);
    }
}
