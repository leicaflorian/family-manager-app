<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BankConnectionStatus;
use App\Models\BankConnection;
use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankConnection>
 */
final class BankConnectionFactory extends Factory
{
    /**
     * An active connection of the fake provider, without a session yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'provider' => 'fake',
            'institution_name' => fake()->unique()->randomElement(['Intesa Sanpaolo', 'UniCredit', 'BPER Banca', 'Fineco', 'Banco BPM']),
            'institution_country' => 'IT',
            'status' => BankConnectionStatus::Active,
        ];
    }
}
