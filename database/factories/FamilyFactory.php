<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Family>
 */
final class FamilyFactory extends Factory
{
    /**
     * Una famiglia con un cognome italiano.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->lastName(),
        ];
    }
}
