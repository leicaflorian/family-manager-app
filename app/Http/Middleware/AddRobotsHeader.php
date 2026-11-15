<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\PageMeta;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps search engines out of every page but *Log in* and *Sign up* with `X-Robots-Tag`. It is global, so it also
 * covers what is not a page (the calendar, the service worker) and the 404s, which have no route.
 */
final class AddRobotsHeader
{
    /**
     * Adds `X-Robots-Tag: noindex, nofollow` to the answer of a page that must not be listed, unless the answer has
     * its own rule.
     *
     * @param  Request  $request  the request
     * @param  Closure(Request): Response  $next  the rest of the pipeline
     * @return Response the answer, with the header when needed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // An answer that already says what robots may do (the calendar) keeps its own rule
        if (! PageMeta::indexable($request) && ! $response->headers->has('X-Robots-Tag')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
