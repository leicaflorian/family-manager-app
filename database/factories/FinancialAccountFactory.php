<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatoConto;
use App\Enums\TipoConto;
use App\Models\Family;
use App\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialAccount>
 */
final class FinancialAccountFactory extends Factory
{
    /**
     * Un conto corrente.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'name' => fake()->randomElement(['Conto BPER', 'Conto Intesa', 'Conto Fineco']),
            'type' => TipoConto::CheckingAccount,
            'status' => StatoConto::Active,
            'last_four_digits' => fake()->numerify('####'),
        ];
    }
}
