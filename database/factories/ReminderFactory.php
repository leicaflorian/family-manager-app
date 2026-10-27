<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReminderStatus;
use App\Models\Family;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
final class ReminderFactory extends Factory
{
    /**
     * An open reminder.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'title' => 'Rinnovare '.fake()->word(),
            'due_date' => fake()->dateTimeBetween('now', '+3 months'),
            'status' => ReminderStatus::Open,
        ];
    }
}
