<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

/**
 * Sets a new password for the account of a family, from the terminal: the app has no button for it, a person changes
 * their own from *Forgot password?* or *My account*. The linked phones of the account are signed out by the model,
 * as with every change of password.
 */
#[Description('Set a new password for the account of a family')]
#[Signature('user:password {email : The email the account signs in with}')]
final class SetUserPassword extends Command
{
    /**
     * Finds the account, asks the new password twice and saves it if it follows the rules of the app.
     *
     * @return int `SUCCESS` when the password is saved, `FAILURE` otherwise
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->with('family')->where('email', $email)->first();

        if ($user === null) {
            $this->error(sprintf('No account signs in with %s.', $email));

            return self::FAILURE;
        }

        $this->info(sprintf('Account of %s, family %s.', $user->name, $user->family->name));

        $password = password('New password', required: true);
        $confirmation = password('Confirm the password', required: true);

        if ($password !== $confirmation) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(['password' => $password], ['password' => [Password::defaults()]]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first('password'));

            return self::FAILURE;
        }

        $user->update(['password' => $password]);
        $this->info(sprintf('Password changed for %s: the linked phones of the account are signed out.', $user->name));

        return self::SUCCESS;
    }
}
