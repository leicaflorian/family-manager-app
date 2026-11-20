<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BankTransactionStatus;
use App\Models\BankTransaction;
use App\Models\Family;
use App\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankTransaction>
 */
final class BankTransactionFactory extends Factory
{
    /**
     * A booked outgoing payment in euros, not linked to anything, on an account of the same family.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-2 months')->format('Y-m-d');

        return [
            'family_id' => Family::factory(),
            // Taken from the family the row ends up in (`->for($family)`), not from a second one.
            'financial_account_id' => fn (array $attributes): int => FinancialAccount::factory()->create(['family_id' => $attributes['family_id']])->id,
            'provider_id' => fake()->unique()->uuid(),
            'booked_on' => $date,
            'value_on' => $date,
            'amount' => '-'.fake()->randomFloat(2, 1, 300),
            'currency' => 'EUR',
            'text' => 'Pagamento '.fake()->word(),
            'counterparty' => fake()->company(),
            'status' => BankTransactionStatus::Booked,
        ];
    }
}
