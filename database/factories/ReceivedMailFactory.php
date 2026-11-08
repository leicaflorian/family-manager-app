<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MailOutcome;
use App\Models\Family;
use App\Models\ReceivedMail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceivedMail>
 */
final class ReceivedMailFactory extends Factory
{
    /**
     * A mail with one attachment saved.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $recipient = fake()->unique()->userName().'@bollette.familymanager.test';

        return [
            'family_id' => Family::factory(),
            'event_id' => fake()->unique()->uuid(),
            'sender' => 'Edison <fatture@edison.example>',
            'sender_address' => 'fatture@edison.example',
            'recipient' => $recipient,
            'recipients' => [$recipient],
            'subject' => 'La tua bolletta',
            'received_at' => now(),
            'deliveries' => 1,
            'outcome' => MailOutcome::Saved,
            'notes' => [],
            'skipped' => [],
        ];
    }
}
