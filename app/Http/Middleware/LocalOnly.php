<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request through only when the app runs in the local environment.
 */
final class LocalOnly
{
    /**
     * Answers 404 anywhere but in local, so the dev pages do not exist online.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app()->isLocal(), 404);

        return $next($request);
    }
}
