<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentCategory;
use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentCategory>
 */
final class DocumentCategoryFactory extends Factory
{
    /**
     * Una categoria dei documenti.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'name' => fake()->randomElement(['Casa e utenze', 'Veicoli', 'Salute', 'Lavoro', 'Scuola e attività']),
        ];
    }
}
