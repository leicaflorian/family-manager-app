<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatoFinanziamento;
use App\Models\Family;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
final class LoanFactory extends Factory
{
    /**
     * Un finanziamento.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'name' => 'Finanziamento '.fake()->word(),
            'status' => StatoFinanziamento::Active,
            'total_amount' => fake()->randomFloat(2, 1000, 20000),
            'installments_count' => fake()->numberBetween(12, 60),
        ];
    }
}
