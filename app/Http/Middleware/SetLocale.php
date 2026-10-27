<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chooses the language of the request: the one the browser prefers, among those
 * the app has, or the default one.
 */
final class SetLocale
{
    /** The languages the app is translated into. */
    public const array LOCALES = ['en', 'it'];

    /**
     * Sets the language for the rest of the request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->preferred($request));

        return $next($request);
    }

    /**
     * The first language of `Accept-Language` the app has, or the default one.
     *
     * @param  Request  $request  the request
     */
    private function preferred(Request $request): string
    {
        foreach ($request->getLanguages() as $language) {
            $base = strtolower(substr($language, 0, 2));

            if (in_array($base, self::LOCALES, true)) {
                return $base;
            }
        }

        return config()->string('app.locale');
    }
}
