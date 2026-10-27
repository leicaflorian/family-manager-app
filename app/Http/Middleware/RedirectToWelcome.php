<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until the family has finished the *Welcome*, the pages of the app lead there.
 */
final class RedirectToWelcome
{
    /**
     * Sends to the *Welcome* whoever signs in to a family not welcomed yet.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->family->isWelcomed()) {
            return to_route('welcome');
        }

        return $next($request);
    }
}
