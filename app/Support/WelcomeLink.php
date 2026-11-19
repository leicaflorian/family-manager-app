<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\WelcomeLinkController;
use App\Models\User;

/**
 * The link of the welcome mail of a family created from the owner panel: signed, valid for {@see self::DAYS} days,
 * on the host of the app, tied to the email it was sent to. {@see WelcomeLinkController} trades it for the page that
 * chooses the password, until the password is saved.
 */
final class WelcomeLink
{
    /** How many days the link lasts. */
    public const int DAYS = 7;

    /**
     * The link for an account.
     *
     * @param  User  $user  the account that chooses its password
     * @return string the absolute, signed link
     */
    public static function for(User $user): string
    {
        return AppHostUrl::generator()->temporarySignedRoute('welcome.link', now()->addDays(self::DAYS), [
            'user' => $user->id,
            'hash' => self::hash($user),
        ]);
    }

    /**
     * The fingerprint of the email the link is sent to: after the account changes its email, an old link does not
     * open anything (like the `hash` of Laravel's link that confirms an email).
     *
     * @param  User  $user  the account
     */
    public static function hash(User $user): string
    {
        return sha1($user->email);
    }
}
