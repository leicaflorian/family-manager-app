<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * nginx (production image) answers a path that is a folder of public/ by itself: `/mail` became `/mail/` and then
 * 403, and Laravel never saw the request. Sail serves through PHP, so only production showed it.
 */
test('no folder of public has the path of a page', function (): void {
    // In production the panel sits at the root of its own host (ADMIN_DOMAIN), so its pages are checked without /admin
    $panel = trim(Filament::getPanel('admin')->getPath(), '/');

    $clashes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => in_array('GET', $route->methods(), true))
        ->flatMap(function (RoutingRoute $route) use ($panel): array {
            $uri = trim($route->uri(), '/');

            return $panel !== '' && Str::startsWith($uri, $panel.'/') ? [$uri, Str::after($uri, $panel.'/')] : [$uri];
        })
        ->flatMap(function (string $uri): array {
            // Every leading part of the path before the first parameter: /a/b/{id} is checked as a and a/b
            $prefixes = [];
            $path = '';

            foreach (explode('/', $uri) as $segment) {
                if ($segment === '' || str_contains($segment, '{')) {
                    break;
                }

                $path = ltrim($path.'/'.$segment, '/');
                $prefixes[] = $path;
            }

            return $prefixes;
        })
        ->unique()
        ->filter(fn (string $path): bool => is_dir(public_path($path)))
        ->values()
        ->all();

    expect($clashes)->toBe([]);
});
