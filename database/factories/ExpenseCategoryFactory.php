<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
final class ExpenseCategoryFactory extends Factory
{
    /**
     * Una categoria delle spese.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'name' => fake()->randomElement(['Casa', 'Spesa', 'Auto', 'Salute']),
        ];
    }
}
