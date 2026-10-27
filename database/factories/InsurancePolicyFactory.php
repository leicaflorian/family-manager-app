<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InsurancePolicyStatus;
use App\Enums\InsurancePolicyType;
use App\Models\Family;
use App\Models\InsurancePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InsurancePolicy>
 */
final class InsurancePolicyFactory extends Factory
{
    /**
     * A car insurance policy.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'title' => 'RC auto',
            'type' => InsurancePolicyType::Car,
            'status' => InsurancePolicyStatus::Active,
            'policy_number' => fake()->numerify('#########'),
            'expiry_date' => fake()->dateTimeBetween('now', '+1 year'),
        ];
    }
}
