<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatoLavoro;
use App\Models\Employment;
use App\Models\Family;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employment>
 */
final class EmploymentFactory extends Factory
{
    /**
     * Un lavoro di una persona.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'person_id' => fn (array $attributi) => Person::factory()->state(['family_id' => $attributi['family_id']]),
            'job_title' => fake()->jobTitle(),
            'status' => StatoLavoro::Active,
        ];
    }
}
