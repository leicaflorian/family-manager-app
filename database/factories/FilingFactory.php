<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FilingStatus;
use App\Enums\FilingType;
use App\Models\Family;
use App\Models\Filing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Filing>
 */
final class FilingFactory extends Factory
{
    /**
     * A filing.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'title' => 'ISEE '.fake()->year(),
            'type' => FilingType::Isee,
            'status' => FilingStatus::Draft,
            'reference_year' => fake()->numberBetween(2020, 2027),
        ];
    }
}
