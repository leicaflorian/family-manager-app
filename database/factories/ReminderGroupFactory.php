<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Family;
use App\Models\ReminderGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderGroup>
 */
final class ReminderGroupFactory extends Factory
{
    /**
     * An alert group.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'name' => 'Scadenze',
            'is_default' => false,
        ];
    }
}
