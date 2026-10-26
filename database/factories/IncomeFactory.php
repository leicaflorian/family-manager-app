<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoEntrata;
use App\Models\Family;
use App\Models\Income;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Income>
 */
final class IncomeFactory extends Factory
{
    /**
     * Un'entrata incassata.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'title' => 'Stipendio',
            'amount' => fake()->randomFloat(2, 1200, 2500),
            'date' => fake()->dateTimeBetween('-6 months'),
            'type' => TipoEntrata::Salary,
        ];
    }
}
