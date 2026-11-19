<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Support\WelcomeLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * The link of the welcome mail ({@see WelcomeLink}), opened by whoever has not chosen a password yet.
 */
final class WelcomeLinkController extends Controller
{
    /**
     * Opens the page of the new password with a fresh token, which lasts like the one of a forgotten password. The
     * opening changes nothing else, so a mail scanner that opens the link first does not use it up: the email is
     * confirmed when the password is saved ({@see ResetUserPassword}). Once it is confirmed, or when the account has
     * another email than the one the link was sent to, the link goes to the sign in with a message.
     *
     * @param  Request  $request  the request, with the fingerprint of the email
     * @param  User  $user  the account of the link
     */
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        if ($user->email_verified_at !== null || ! hash_equals(WelcomeLink::hash($user), (string) $request->query('hash'))) {
            return to_route('login')->with('status', __('This link was already used. To sign in, use Forgot password.'));
        }

        return to_route('password.reset', [
            'token' => Password::broker()->createToken($user),
            'email' => $user->email,
        ]);
    }
}
