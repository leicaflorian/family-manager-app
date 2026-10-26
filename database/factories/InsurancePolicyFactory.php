<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatoPolizza;
use App\Enums\TipoPolizza;
use App\Models\Family;
use App\Models\InsurancePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InsurancePolicy>
 */
final class InsurancePolicyFactory extends Factory
{
    /**
     * Una polizza dell'auto.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'title' => 'RC auto',
            'type' => TipoPolizza::Car,
            'status' => StatoPolizza::Active,
            'policy_number' => fake()->numerify('#########'),
            'expiry_date' => fake()->dateTimeBetween('now', '+1 year'),
        ];
    }
}
