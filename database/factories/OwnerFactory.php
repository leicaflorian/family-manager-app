<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Owner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Owner>
 */
final class OwnerFactory extends Factory
{
    /** The password being used by the factory, hashed once. */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * An owner who already set up the authenticator app.
     */
    public function withAuthenticator(): static
    {
        return $this->state(fn (array $attributes): array => [
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
            'app_authentication_recovery_codes' => ['aaaaaaaaaa', 'bbbbbbbbbb'],
        ]);
    }
}
