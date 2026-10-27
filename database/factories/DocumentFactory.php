<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Family;
use App\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
final class DocumentFactory extends Factory
{
    /**
     * A document with its file.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'title' => fake()->randomElement(['Bolletta luce', 'Busta paga', 'Polizza RC auto', 'Scontrino']),
            'status' => DocumentStatus::Active,
            'date' => fake()->dateTimeBetween('-1 year'),
            'file_id' => fn (array $attributes) => File::factory()->state(['family_id' => $attributes['family_id']]),
        ];
    }
}
