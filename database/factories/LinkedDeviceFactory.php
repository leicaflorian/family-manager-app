<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Family;
use App\Models\LinkedDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LinkedDevice>
 */
final class LinkedDeviceFactory extends Factory
{
    /**
     * A phone that entered now, of an account of the same family.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            // Taken from the family the row ends up in (`->for($family)`), not from a second one.
            'user_id' => fn (array $attributes): int => User::factory()->create(['family_id' => $attributes['family_id']])->id,
            'name' => fake()->randomElement(['iPhone', 'Pixel', 'Galaxy']),
            'token_hash' => hash('sha256', Str::random(40)),
            'session_id' => null,
            'last_used_at' => now(),
            'last_upload_at' => null,
        ];
    }
}
