<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a phone linked with a code on the *Scan*: with `linked_device_id` in the session only the routes of
 * {@see self::ALLOWED} open. A `GET` elsewhere goes to the scan, any other method is refused.
 *
 * `connect` is on the list because the page to write the code is also where a phone resumes its link; `logout`
 * lets the person leave. A request that matched no named route (a 404) is not touched.
 */
final class LimitLinkedDevice
{
    /** The route names a linked phone can open. */
    public const array ALLOWED = ['scan', 'scan.*', 'connect', 'connect.*', 'logout'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->has('linked_device_id')) {
            return $next($request);
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
