<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\HealthEventType;
use App\Models\Family;
use App\Models\HealthEvent;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthEvent>
 */
final class HealthEventFactory extends Factory
{
    /**
     * A visit of a person.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'title' => fake()->randomElement(['Visita pediatrica', 'Visita oculistica', 'Visita dentistica']),
            'type' => HealthEventType::Visit,
            'person_id' => fn (array $attributi) => Person::factory()->state(['family_id' => $attributi['family_id']]),
            'date' => fake()->dateTimeBetween('-1 year'),
        ];
    }
}
