<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes `users.last_seen_at` for the account of the `web` guard, at most once a minute. A visit of an owner
 * (session key `visit`) is not the account's own access and writes nothing.
 */
final class RecordLastSeen
{
    /** Seconds between two writes for the same account. */
    private const int EVERY_SECONDS = 60;

    /**
     * Records the access and goes on.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user instanceof User && $request->hasSession() && ! $request->session()->has('visit')) {
            $seen = $user->last_seen_at;

            if ($seen === null || $seen->lt(now()->subSeconds(self::EVERY_SECONDS))) {
                // A plain query: the model is not touched, so `updated_at` stays the account's own
                DB::table('users')->where('id', $user->id)->update(['last_seen_at' => now()]);
                $user->setAttribute('last_seen_at', now());
                $user->syncOriginal();
            }
        }

        return $next($request);
    }
}
