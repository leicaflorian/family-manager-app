<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\LinkedDevice;
use App\Services\PhoneLinks;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Brings a linked phone back in when its session is over: the cookie holds the secret of the phone, and the
 * account of that phone enters without a password. It runs after the session and before `auth`.
 */
final readonly class SignInLinkedDevice
{
    /** The pages where the phone is not brought back in: signing in with the password, and getting a new one. */
    private const array PASSWORD_ROUTES = ['login', 'login.store', 'password.request', 'password.email', 'password.reset', 'password.update'];

    /**
     * @param  PhoneLinks  $links  the linking of the phones
     */
    public function __construct(private PhoneLinks $links) {}

    /**
     * Enters the phone of the cookie, if nobody is signed in and the secret is the one of a phone not expired.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $request->cookie(PhoneLinks::COOKIE);

        // Not on the password pages: after signing out, the person must be able to use (or get back) the password here
        if (is_string($secret) && $secret !== '' && ! Auth::check() && ! $request->routeIs(self::PASSWORD_ROUTES)) {
            $device = LinkedDevice::findByToken($secret);

            if ($device instanceof LinkedDevice) {
                $this->links->signIn($device, $request);
            } else {
                // Forgotten or expired: the cookie is of no use any more, so it stops costing a query at every request
                Cookie::queue(Cookie::forget(PhoneLinks::COOKIE));
            }
        }

        return $next($request);
    }
}
