<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Admin\EndVisit;
use App\Models\Owner;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * During a visit of the owner as an account (session key `visit`): the owner only looks, every request that is not
 * `GET` or `HEAD` answers 403 except the routes of {@see self::ALLOWED}; and the visit ends, back to the panel, when
 * the owner is no longer signed into it, after {@see self::MINUTES} minutes, or when the family took back the
 * permission, let it expire or was blocked. It runs before {@see BlockSuspendedFamily}, which would sign the owner
 * out of the panel too (the session is the same).
 */
final readonly class LimitVisit
{
    /** How long a visit lasts at most. */
    public const int MINUTES = 60;

    /** The routes a visit may send with a method that changes something: only leaving the visit. */
    public const array ALLOWED = ['visit.leave'];

    /**
     * @param  EndVisit  $end  ends the visit
     */
    public function __construct(private EndVisit $end) {}

    /**
     * Ends the visit or lets the request through, read-only.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->has('visit')) {
            return $next($request);
        }

        if ($this->isOver($request->session()->get('visit'))) {
            $this->end->handle($request->session());

            return Inertia::location(route('filament.admin.pages.dashboard'));
        }

        abort_if(! in_array($request->method(), ['GET', 'HEAD'], true) && ! $request->routeIs(...self::ALLOWED), 403);

        return $next($request);
    }

    /**
     * Whether the visit has to end now.
     *
     * @param  mixed  $visit  the session key `visit`
     */
    private function isOver(mixed $visit): bool
    {
        if (! is_array($visit) || ! is_int($visit['owner_id'] ?? null) || ! is_int($visit['user_id'] ?? null) || ! is_int($visit['started_at'] ?? null)) {
            return true;
        }

        $owner = Auth::guard('owner')->user();
        $user = Auth::guard('web')->user();

        return ! $owner instanceof Owner || $owner->id !== $visit['owner_id']
            || ! $user instanceof User || $user->id !== $visit['user_id']
            || now()->getTimestamp() - $visit['started_at'] >= self::MINUTES * 60
            // Read again: the family may have changed during the visit
            || ! $user->family()->firstOrFail()->allowsSupport();
    }
}
