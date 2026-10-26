<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoEccezione;
use App\Models\Family;
use App\Models\PlannedIncome;
use App\Models\PlannedIncomeException;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlannedIncomeException>
 */
final class PlannedIncomeExceptionFactory extends Factory
{
    /**
     * Un'eccezione di un'entrata prevista.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'planned_income_id' => fn (array $attributi) => PlannedIncome::factory()->state(['family_id' => $attributi['family_id']]),
            'type' => TipoEccezione::AmountOverride,
            'occurrence_date' => fake()->dateTimeBetween('now', '+6 months'),
            'amount_override' => fake()->randomFloat(2, 100, 500),
        ];
    }
}
