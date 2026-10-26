<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Family;
use App\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<File>
 */
final class FileFactory extends Factory
{
    /**
     * Un PDF finto nel disco privato.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'disk' => 'local',
            'path' => 'families/0/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1000, 500000),
        ];
    }
}
