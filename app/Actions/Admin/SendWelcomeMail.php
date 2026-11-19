<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Sends the welcome mail of an account created from the owner panel, in the language of the account.
 */
final readonly class SendWelcomeMail
{
    /**
     * Sends the mail. A mail that does not go out is reported and told, not thrown: the account is already there,
     * and the panel says to send it again.
     *
     * @param  User  $user  the account, with the language chosen when it was created (the panel's when it has none)
     * @return bool whether the mail went out
     */
    public function handle(User $user): bool
    {
        try {
            Mail::to($user)->locale($user->locale ?? app()->getLocale())->send(new WelcomeMail($user));
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return false;
        }

        return true;
    }
}
