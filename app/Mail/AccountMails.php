<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The mails about the account that Laravel sends (a new password, the confirmation of the email),
 * worded and drawn like the other mails of Fameja.app (boards "Le email" of the canvas).
 */
final class AccountMails
{
    /**
     * The mail with the link to choose a new password.
     *
     * @param  mixed  $notifiable  who asked for it
     * @param  string  $token  the token of the link
     */
    public static function resetPassword(mixed $notifiable, string $token): MailMessage
    {
        $email = $notifiable instanceof CanResetPassword ? $notifiable->getEmailForPasswordReset() : '';
        $minutes = config('auth.passwords.users.expire');

        return new MailMessage()
            ->subject(__('Choose a new password'))
            ->markdown('mail.reset-password', [
                'name' => self::firstName($notifiable),
                'url' => route('password.reset', ['token' => $token, 'email' => $email]),
                'lasts' => self::lasts(is_int($minutes) ? $minutes : 60),
            ]);
    }

    /**
     * The mail with the link that confirms the email of the account.
     *
     * @param  mixed  $notifiable  who signed up
     * @param  string  $url  the signed link
     */
    public static function verifyEmail(mixed $notifiable, string $url): MailMessage
    {
        $minutes = config('auth.verification.expire', 60);

        return new MailMessage()
            ->subject(__('Confirm your email'))
            ->markdown('mail.verify-email', [
                'name' => self::firstName($notifiable),
                'url' => $url,
                'lasts' => self::lasts(is_int($minutes) ? $minutes : 60),
            ]);
    }

    /**
     * How long a link lasts, in whole hours (at least one).
     *
     * @param  int  $minutes  the minutes of the link
     */
    private static function lasts(int $minutes): string
    {
        $hours = max(1, intdiv($minutes, 60));

        return trans_choice('{1} The link is valid for one hour.|[2,*] The link is valid for :count hours.', $hours);
    }

    /**
     * The first name to greet with, empty when the mail is not for an account.
     *
     * @param  mixed  $notifiable  who gets the mail
     */
    private static function firstName(mixed $notifiable): string
    {
        return $notifiable instanceof User ? $notifiable->firstName() : '';
    }
}
