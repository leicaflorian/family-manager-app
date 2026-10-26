<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatoPratica;
use App\Enums\TipoPratica;
use App\Models\Family;
use App\Models\Filing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Filing>
 */
final class FilingFactory extends Factory
{
    /**
     * Una pratica.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'title' => 'ISEE '.fake()->year(),
            'type' => TipoPratica::Isee,
            'status' => StatoPratica::Draft,
            'reference_year' => fake()->numberBetween(2020, 2027),
        ];
    }
}
