<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the owner panel and the app on their own hosts when `ADMIN_DOMAIN` is set (`config('app.admin_domain')`): on
 * the admin host only the panel (`filament.*`) and the routes of Livewire it uses answer, everything else is 404; on
 * any other host the panel is 404. The `->domain()` of the panel already binds its routes, this is the safety net.
 * Without `ADMIN_DOMAIN` it does nothing and the panel stays at `/admin`.
 */
final readonly class SeparateHosts
{
    /** The routes that answer on the admin host: the panel and the routes of Livewire, which are global. */
    public const array ADMIN_ROUTES = ['filament.*', ...LimitVisit::PANEL, 'livewire.preview-file'];

    /**
     * The middleware for the routes outside the `web` group (calendar, Mailgun, service worker, *Share*):
     * `withoutMiddleware('web')` takes away every class of the group, this one too, but not a name with a parameter (the
     * parameter itself is not read).
     */
    public const string OUTSIDE_WEB = self::class.':outside-web';

    /**
     * Whether the panel has its own host (`ADMIN_DOMAIN` set): then the panel and the app have two sessions.
     */
    public static function active(): bool
    {
        $domain = config('app.admin_domain');

        return is_string($domain) && $domain !== '';
    }

    /**
     * Whether the request arrived on the host of the owner panel. Always false without `ADMIN_DOMAIN`.
     *
     * @param  Request  $request  the request
     */
    public static function onAdminHost(Request $request): bool
    {
        return self::active() && $request->getHost() === strtolower(config()->string('app.admin_domain'));
    }

    /**
     * Answers 404 to a route on the wrong host, or lets the request through.
     *
     * @param  Request  $request  the request
     * @param  Closure(Request): Response  $next  the next middleware
     * @return Response the response of the next middleware
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (self::onAdminHost($request)) {
            abort_unless($request->routeIs(...self::ADMIN_ROUTES), 404);
        } elseif (self::active()) {
            abort_if($request->routeIs('filament.*'), 404);
        }

        return $next($request);
    }
}
