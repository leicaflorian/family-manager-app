<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Services\PhoneLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

/**
 * Where signing out leads. A linked phone goes to the sign-in: anywhere else its cookie would bring it back in at
 * once (*Sign in with the password* on the scan). Every other account goes to the start, as Fortify does.
 */
final class LogoutResponse implements LogoutResponseContract
{
    /**
     * The answer to signing out.
     *
     * @param  Request  $request  the request that signed out
     */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $secret = $request->cookie(PhoneLinks::COOKIE);

        return is_string($secret) && $secret !== '' ? to_route('login') : redirect('/');
    }
}
