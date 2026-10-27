<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Enums\PersonRole;
use App\Enums\Role;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Creates the invitation to join the current family and sends it by email.
 */
final class SendInvitation
{
    /**
     * Creates the invitation, valid for 7 days, and sends the email with the link.
     *
     * @param  array{email: string, role: string, person_id?: int|null, first_name?: string|null, last_name?: string|null, person_role?: string|null}  $data  the data of the form, already checked
     * @return Invitation the invitation created
     */
    public function handle(array $data): Invitation
    {
        $forPerson = isset($data['person_id']);

        $invitation = Invitation::query()->create([
            'email' => Str::lower($data['email']),
            'role' => Role::from($data['role']),
            'person_id' => $data['person_id'] ?? null,
            'first_name' => $forPerson ? null : ($data['first_name'] ?? null),
            'last_name' => $forPerson ? null : ($data['last_name'] ?? null),
            'person_role' => $forPerson ? null : PersonRole::tryFrom($data['person_role'] ?? ''),
            'token' => Str::random(64),
            'expires_at' => now()->addDays(Invitation::DAYS),
        ]);

        Mail::to($invitation->email)->locale(app()->getLocale())->send(new InvitationMail($invitation));

        return $invitation;
    }
}
