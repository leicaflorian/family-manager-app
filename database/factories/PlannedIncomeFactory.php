<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoEntrata;
use App\Enums\UnitaIntervallo;
use App\Models\Family;
use App\Models\PlannedIncome;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlannedIncome>
 */
final class PlannedIncomeFactory extends Factory
{
    /**
     * Uno stipendio previsto ogni mese.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'title' => 'Stipendio',
            'amount' => fake()->randomFloat(2, 1200, 2500),
            'type' => TipoEntrata::Salary,
            'is_recurring' => true,
            'interval' => 1,
            'interval_unit' => UnitaIntervallo::Month,
            'start_date' => fake()->dateTimeBetween('-1 year'),
        ];
    }
}
