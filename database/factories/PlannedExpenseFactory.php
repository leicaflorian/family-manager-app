<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IntervalUnit;
use App\Models\Family;
use App\Models\PlannedExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlannedExpense>
 */
final class PlannedExpenseFactory extends Factory
{
    /**
     * A planned expense every month.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'title' => fake()->randomElement(['Bolletta luce', 'Bolletta gas', 'Bolletta acqua']),
            'amount' => fake()->randomFloat(2, 20, 200),
            'is_recurring' => true,
            'interval' => 1,
            'interval_unit' => IntervalUnit::Month,
            'start_date' => fake()->dateTimeBetween('-1 year'),
        ];
    }
}
