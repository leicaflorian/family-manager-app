<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
final class ExpenseFactory extends Factory
{
    /**
     * Una spesa pagata.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'title' => 'Spesa '.fake()->word(),
            'amount' => fake()->randomFloat(2, 5, 300),
            'date' => fake()->dateTimeBetween('-6 months'),
        ];
    }
}
