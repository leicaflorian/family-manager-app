<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\LinkedDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a phone linked with a code on the *Scan*: with `linked_device_id` in the session only the routes of
 * {@see self::ALLOWED} open. A `GET` elsewhere goes to the scan, any other method is refused.
 *
 * `connect` is on the list because the page to write the code is also where a phone resumes its link; `logout`
 * lets the person leave; `verification.*` lets a phone whose email is not verified reach the notice and the link of the
 * mail, which the `verified` middleware of the scan would otherwise send in a loop. A phone forgotten from the
 * computer is signed out at its next request, whatever the session driver. A request that matched no named route (a 404) is not touched.
 */
final class LimitLinkedDevice
{
    /** The route names a linked phone can open. */
    public const array ALLOWED = ['scan', 'scan.*', 'connect', 'connect.*', 'logout', 'verification.*'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->has('linked_device_id')) {
            return $next($request);
        }

        $deviceId = $request->session()->get('linked_device_id');

        if (! is_int($deviceId) || ! LinkedDevice::isActive($deviceId)) {
            // Forgotten or expired: the session of the phone must not outlive its link
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login');
        }

        $route = $request->route();

        // No route or no name: a 404 or a route outside the app pages, nothing to protect
        if ($route === null || $route->getName() === null || $request->routeIs(self::ALLOWED)) {
            return $next($request);
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return to_route('scan');
        }

        abort(403);
    }
}
