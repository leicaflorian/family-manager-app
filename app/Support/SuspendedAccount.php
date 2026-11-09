<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Owner;
use Illuminate\Validation\ValidationException;

/**
 * What a person who belongs to a suspended family is told, at the sign-in and when they are sent out.
 */
final class SuspendedAccount
{
    /**
     * The message, with the email of the first owner (or the address the app writes from when there is none).
     */
    public static function message(): string
    {
        $email = Owner::query()->orderBy('id')->value('email');

        return __('Your account is suspended. Write to :email to know why.', [
            'email' => is_string($email) ? $email : config()->string('mail.from.address'),
        ]);
    }

    /**
     * The error of the sign-in form for a suspended family.
     */
    public static function exception(): ValidationException
    {
        return ValidationException::withMessages(['email' => self::message()]);
    }
}
