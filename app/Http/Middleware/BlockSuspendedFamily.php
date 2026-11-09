<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Family;
use App\Models\User;
use App\Support\SuspendedAccount;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends out the account of a suspended family (or one waiting to be deleted) at its next request, a linked phone
 * included, with the message of the suspension on the sign-in.
 */
final class BlockSuspendedFamily
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        $family = Family::query()->find($user->family_id);

        if (! $family instanceof Family || ! $family->isBlocked()) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 303: a PUT, PATCH or DELETE must go to the sign-in with a GET (this runs before the Inertia middleware)
        return to_route('login', [], 303)->withErrors(['email' => SuspendedAccount::message()]);
    }
}
