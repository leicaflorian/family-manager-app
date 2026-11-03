<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReadingPurpose;
use App\Models\AiReading;
use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiReading>
 */
final class AiReadingFactory extends Factory
{
    /**
     * A reading of a document, without a file or an account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'purpose' => ReadingPurpose::Document,
            'provider' => 'anthropic',
            'model' => 'claude-haiku-4-5',
            'input_tokens' => fake()->numberBetween(500, 5000),
            'output_tokens' => fake()->numberBetween(50, 500),
            'cache_read_tokens' => 0,
            'cost_usd' => '0.002500',
        ];
    }
}
