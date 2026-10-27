<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PersonRole;
use App\Models\Family;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
final class PersonFactory extends Factory
{
    /**
     * A person with an Italian first and last name and their initials.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->firstName();
        $cognome = fake()->lastName();

        return [
            'family_id' => Family::factory(),
            'first_name' => $nome,
            'last_name' => $cognome,
            'initials' => mb_strtoupper(mb_substr($nome, 0, 1).mb_substr($cognome, 0, 1)),
            'role' => PersonRole::Parent,
        ];
    }
}
