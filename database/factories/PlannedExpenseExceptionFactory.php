<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoEccezione;
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
     * Un'eccezione che salta una scadenza.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'planned_expense_id' => fn (array $attributi) => PlannedExpense::factory()->state(['family_id' => $attributi['family_id']]),
            'type' => TipoEccezione::Skip,
            'occurrence_date' => fake()->dateTimeBetween('now', '+6 months'),
        ];
    }
}
