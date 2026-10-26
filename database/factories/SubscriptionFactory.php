<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatoAbbonamento;
use App\Enums\TipoAbbonamento;
use App\Models\Family;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
final class SubscriptionFactory extends Factory
{
    /**
     * Un abbonamento in streaming.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'name' => fake()->randomElement(['Netflix', 'Spotify', 'iCloud']),
            'type' => TipoAbbonamento::Streaming,
            'status' => StatoAbbonamento::Active,
            'is_auto_renew' => true,
        ];
    }
}
