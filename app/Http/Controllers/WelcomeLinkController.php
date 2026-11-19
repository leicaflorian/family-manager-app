<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\WelcomeLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;

/**
 * The link of the welcome mail ({@see WelcomeLink}), opened by whoever has not chosen a password yet.
 */
final class WelcomeLinkController extends Controller
{
    /**
     * Confirms the email and opens the page of the new password with a fresh token, which lasts like the one of a
     * forgotten password. Once the email is confirmed the link is used: it goes to the sign in with a message.
     *
     * @param  User  $user  the account of the link
     */
    public function __invoke(User $user): RedirectResponse
    {
        if ($user->email_verified_at !== null) {
            return to_route('login')->with('status', __('This link was already used. To sign in, use Forgot password.'));
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        return to_route('password.reset', [
            'token' => Password::broker()->createToken($user),
            'email' => $user->email,
        ]);
    }
}
