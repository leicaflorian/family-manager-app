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
        $locale = $this->preferred($request);
        app()->setLocale($locale);
        $this->remember($request, $locale);

        return $next($request);
    }

    /**
     * Saves the language in the account, for the email the morning round writes: one query, and only when it changed.
     *
     * @param  Request  $request  the request
     * @param  string  $locale  the language chosen for it
     */
    private function remember(Request $request, string $locale): void
    {
        $user = $request->user();

        if ($user !== null && $user->locale !== $locale) {
            $user->forceFill(['locale' => $locale])->saveQuietly();
        }
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
