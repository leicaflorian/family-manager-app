<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Actions\People\SavePerson;
use App\Enums\PersonRole;
use App\Models\Invitation;
use App\Models\User;
use App\Support\CurrentFamily;
use Illuminate\Support\Facades\DB;

/**
 * Accepts an invitation: the account is created, tied to the person of the invitation or to a new one.
 */
final readonly class AcceptInvitation
{
    /**
     * @param  SavePerson  $savePerson  saves the person of the account
     */
    public function __construct(private SavePerson $savePerson) {}

    /**
     * Creates the account with the level of the invitation and ties it to the
     * person. The email is already verified: the link arrived right there.
     *
     * @param  Invitation  $invitation  a valid invitation (see `Invitation::findValid()`)
     * @param  string  $password  the chosen password
     * @return User the account created
     */
    public function handle(Invitation $invitation, string $password): User
    {
        return DB::transaction(fn (): User => CurrentFamily::using($invitation->family_id, function () use ($invitation, $password): User {
            $person = $invitation->person ?? $this->savePerson->handle([
                'first_name' => (string) $invitation->first_name,
                'last_name' => (string) $invitation->last_name,
                'role' => $invitation->person_role ?? PersonRole::Other,
            ]);

            $account = User::query()->create([
                'family_id' => $invitation->family_id,
                'name' => trim($person->first_name.' '.$person->last_name),
                'email' => $invitation->email,
                'password' => $password,
                'role' => $invitation->role,
            ]);
            $account->forceFill(['email_verified_at' => now()])->save();

            $person->update(['user_id' => $account->id]);
            $invitation->update(['accepted_at' => now(), 'person_id' => $person->id]);

            return $account;
        }));
    }
}
