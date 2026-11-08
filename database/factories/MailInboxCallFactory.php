<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CallOutcome;
use App\Models\MailInboxCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailInboxCall>
 */
final class MailInboxCallFactory extends Factory
{
    /**
     * A call that saved its one attachment, with no family.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'outcome' => CallOutcome::Saved,
            'http_status' => 200,
            'event_id' => fake()->uuid(),
            'attachments' => 1,
            'saved' => 1,
            'payload' => [],
            'created_at' => now(),
        ];
    }
}
