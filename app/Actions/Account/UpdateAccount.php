<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Actions\People\SavePerson;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Changes the first name, last name and email of an account, and the name on its person too.
 */
final readonly class UpdateAccount
{
    /**
     * @param  SavePerson  $savePerson  saves the person of the account
     */
    public function __construct(private SavePerson $savePerson) {}

    /**
     * Saves the data. A new email has to be verified again.
     *
     * @param  User  $user  the account
     * @param  string  $firstName  the first name
     * @param  string  $lastName  the last name
     * @param  string  $email  the email to sign in with
     */
    public function handle(User $user, string $firstName, string $lastName, string $email): void
    {
        DB::transaction(function () use ($user, $firstName, $lastName, $email): void {
            $user->fill(['name' => "{$firstName} {$lastName}", 'email' => $email]);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();
            if ($user->person !== null) {
                $this->savePerson->handle(['first_name' => $firstName, 'last_name' => $lastName], $user->person);
            }
        });

        if ($user->email_verified_at === null) {
            $user->sendEmailVerificationNotification();
        }
    }
}
