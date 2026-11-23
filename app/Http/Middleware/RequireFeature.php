<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Feature;
use App\Services\FamilyFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `feature:<value>`: the route exists only for the families that have the feature, the others get 404.
 */
final readonly class RequireFeature
{
    /**
     * @param  FamilyFeatures  $features  says which features the family has
     */
    public function __construct(private FamilyFeatures $features) {}

    /**
     * @param  Closure(Request): Response  $next
     * @param  string  $feature  the value of a `Feature`
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless($this->features->on(Feature::from($feature)), 404);

        return $next($request);
    }
}
