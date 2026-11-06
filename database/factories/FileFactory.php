<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Data\Reading\Extraction;
use App\Enums\ReadingStatus;
use App\Models\Family;
use App\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<File>
 */
final class FileFactory extends Factory
{
    /**
     * A fake PDF on the private disk.
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

    /**
     * A file read by the AI, as the reading writes it in `files.reading`: what the model read, who read it and when.
     * The stored proposal of the document is left out: a test that needs it adds it with its own state.
     *
     * @param  array<string, mixed>  $extraction  the fields the model read; the confidence is high unless given
     */
    public function read(array $extraction): static
    {
        return $this->state(fn (): array => [
            'reading_status' => ReadingStatus::Done,
            'reading' => [
                'extraction' => Extraction::from(['confidence' => 'high', ...$extraction])->toArray(),
                'provider' => 'gemini',
                'model' => 'gemini-3.8-flash',
                'read_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
