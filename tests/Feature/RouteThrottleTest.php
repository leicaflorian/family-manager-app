<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * Prefixes that more routes share on purpose: both routes do the same costly thing, so they count together.
 *
 * @var array<string, list<string>>
 */
const ROUTE_THROTTLE_SHARED = [
    // Aggiorna on the Dalla banca tab and on the linked banks page both read the bank.
    'bank-sync' => ['banks.sync', 'budget.bank.sync'],
    // Adding, correcting and deleting a consumption by hand are the same panel of the Consumption page.
    'consumption-save' => ['budget.consumption.destroy', 'budget.consumption.store', 'budget.consumption.update'],
    // Fortify gives one throttle to the email confirmation link and to sending it again (config/fortify.php).
    'verification' => ['verification.send', 'verification.verify'],
];

/**
 * The throttles of every route, as the parameters after `throttle:`, keyed by route name (or URI without one).
 *
 * @return array<string, list<list<string>>>
 */
function routeThrottleParameters(): array
{
    $throttles = [];

    /** @var RoutingRoute $route */
    foreach (Route::getRoutes()->getRoutes() as $route) {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            [$name, $parameters] = array_pad(explode(':', $middleware, 2), 2, '');

            if ($name === 'throttle' || $name === ThrottleRequests::class) {
                $throttles[$route->getName() ?? $route->uri()][] = explode(',', $parameters);
            }
        }
    }

    return $throttles;
}

test('every throttle has a named limiter or a prefix of its own', function (): void {
    $throttles = routeThrottleParameters();
    $routesByPrefix = [];

    expect($throttles)->not->toBeEmpty();

    foreach ($throttles as $route => $list) {
        foreach ($list as $parameters) {
            if (count($parameters) === 1) {
                expect(RateLimiter::limiter($parameters[0]))->not->toBeNull("{$route}: no limiter «{$parameters[0]}»");

                continue;
            }

            expect($parameters)->toHaveCount(3, "{$route}: throttle:".implode(',', $parameters).' has no prefix, so it counts with every other throttle of the same person');
            $routesByPrefix[$parameters[2]][] = $route;
        }
    }

    foreach ($routesByPrefix as $prefix => $routes) {
        sort($routes);
        expect($routes)->toBe(ROUTE_THROTTLE_SHARED[$prefix] ?? [$routes[0]], "prefix «{$prefix}» is used by ".implode(', ', $routes));
    }
});

test('the requests to one route do not use up the limit of another', function (): void {
    foreach (range(1, 60) as $ignored) {
        $this->getJson(route('version'))->assertOk();
    }

    $this->get('/calendar/not-a-secret.ics')->assertNotFound();
});
