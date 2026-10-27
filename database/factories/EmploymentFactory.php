<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmploymentStatus;
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
     * A person's employment.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'person_id' => fn (array $attributes) => Person::factory()->state(['family_id' => $attributes['family_id']]),
            'job_title' => fake()->jobTitle(),
            'status' => EmploymentStatus::Active,
        ];
    }
}
