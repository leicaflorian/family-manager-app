<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Family;
use App\Models\Reminder;
use App\Models\ReminderNotice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderNotice>
 */
final class ReminderNoticeFactory extends Factory
{
    /**
     * An unread notice, sent now, of a reminder and an account of the same family.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            // Taken from the family the row ends up in (`->for($family)`), not from a second one.
            'reminder_id' => fn (array $attributes): int => Reminder::factory()->create(['family_id' => $attributes['family_id']])->id,
            'user_id' => fn (array $attributes): int => User::factory()->create(['family_id' => $attributes['family_id']])->id,
            'due_date' => fake()->dateTimeBetween('now', '+1 month'),
            'offset' => -7,
            'sent_at' => now(),
            'read_at' => null,
        ];
    }
}
