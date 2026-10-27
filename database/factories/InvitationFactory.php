<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Family;
use App\Models\Invitation;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
final class InvitationFactory extends Factory
{
    /**
     * An invitation for an existing person, valid for 7 days.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::Member,
            'person_id' => fn (array $attributes) => Person::factory()->state(['family_id' => $attributes['family_id']]),
            'token' => Str::random(64),
            'expires_at' => now()->addDays(Invitation::DAYS),
        ];
    }
}
