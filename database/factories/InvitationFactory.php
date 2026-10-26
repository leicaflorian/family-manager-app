<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Ruolo;
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
     * Un invito per una persona che c'è già, che vale 7 giorni.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Ruolo::Member,
            'person_id' => fn (array $attributi) => Person::factory()->state(['family_id' => $attributi['family_id']]),
            'token' => Str::random(64),
            'expires_at' => now()->addDays(Invitation::GIORNI),
        ];
    }
}
