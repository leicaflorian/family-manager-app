<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatoIscrizione;
use App\Enums\TipoIscrizione;
use App\Models\Enrollment;
use App\Models\Family;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
final class EnrollmentFactory extends Factory
{
    /**
     * Un'iscrizione a un corso.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'person_id' => fn (array $attributi) => Person::factory()->state(['family_id' => $attributi['family_id']]),
            'type' => TipoIscrizione::Sport,
            'status' => StatoIscrizione::Active,
            'school_year' => '2026/27',
        ];
    }
}
