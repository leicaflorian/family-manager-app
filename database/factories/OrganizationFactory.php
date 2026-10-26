<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoEnte;
use App\Models\Family;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
final class OrganizationFactory extends Factory
{
    /**
     * Un ente con un nome e un tipo verosimili.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'name' => fake()->randomElement(['Enel Energia', 'Hera', 'Fastweb', 'UnipolSai', 'Comune di Bologna', 'BPER Banca', 'Studio Ferri']),
            'types' => [TipoEnte::Other],
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
        ];
    }
}
