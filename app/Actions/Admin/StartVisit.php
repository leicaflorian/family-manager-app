<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\OwnerActionType;
use App\Http\Middleware\LimitVisit;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Starts a visit of the owner as an account of a family that gave the support permission: the `web` guard signs in
 * the account, the session keeps `visit` (`owner_id`, `user_id`, `started_at`) and the log gets its line. From here
 * {@see LimitVisit} lets the owner only look, and ends the visit.
 */
final readonly class StartVisit
{
    /**
     * @param  LogOwnerAction  $log  writes the line of the log
     * @param  EndVisit  $end  ends a visit still open in the same session
     */
    public function __construct(private LogOwnerAction $log, private EndVisit $end) {}

    /**
     * Starts the visit.
     *
     * @param  Owner  $owner  the owner of the panel
     * @param  User  $user  the account to look as
     * @param  Session  $session  the session of the request
     *
     * @throws AccessDeniedHttpException when the family has not given the permission, or it expired, or the family is blocked
     */
    public function handle(Owner $owner, User $user, Session $session): void
    {
        // The button shows only with the permission, but it may have expired since the page was drawn
        throw_unless($user->family->allowsSupport(), AccessDeniedHttpException::class);

        $this->end->handle($session);

        Auth::guard('web')->login($user);
        $session->put('visit', ['owner_id' => $owner->id, 'user_id' => $user->id, 'started_at' => now()->getTimestamp()]);

        $this->log->handle(OwnerActionType::VisitStarted, $user->family, $user, $owner);
    }
}
