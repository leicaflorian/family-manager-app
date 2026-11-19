<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\WelcomeLinkController;
use App\Models\User;

/**
 * The link of the welcome mail of a family created from the owner panel: signed, valid for {@see self::DAYS} days,
 * on the host of the app. {@see WelcomeLinkController} trades it, once, for the page that chooses the password.
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
        return AppHostUrl::generator()->temporarySignedRoute('welcome.link', now()->addDays(self::DAYS), ['user' => $user->id]);
    }
}
