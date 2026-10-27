<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExceptionType;
use App\Models\Family;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlannedExpenseException>
 */
final class PlannedExpenseExceptionFactory extends Factory
{
    /**
     * An exception that skips a due date.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'planned_expense_id' => fn (array $attributes) => PlannedExpense::factory()->state(['family_id' => $attributes['family_id']]),
            'type' => ExceptionType::Skip,
            'occurrence_date' => fake()->dateTimeBetween('now', '+6 months'),
        ];
    }
}
