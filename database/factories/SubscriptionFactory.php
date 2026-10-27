<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionType;
use App\Models\Family;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
final class SubscriptionFactory extends Factory
{
    /**
     * A streaming subscription.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'name' => fake()->randomElement(['Netflix', 'Spotify', 'iCloud']),
            'type' => SubscriptionType::Streaming,
            'status' => SubscriptionStatus::Active,
            'is_auto_renew' => true,
        ];
    }
}
