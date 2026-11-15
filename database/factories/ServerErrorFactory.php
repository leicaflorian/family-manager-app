<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServerError;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerError>
 */
final class ServerErrorFactory extends Factory
{
    /**
     * An error with no account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => fake()->uuid(),
            'method' => 'GET',
            'path' => '/'.fake()->slug(2),
            'class' => \RuntimeException::class,
            'message' => fake()->sentence(),
            'file' => '/var/www/html/app/Example.php',
            'line' => fake()->numberBetween(1, 300),
            'created_at' => now(),
        ];
    }
}
