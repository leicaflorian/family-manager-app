<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\OwnerActionType;
use App\Http\Middleware\SeparateHosts;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

/**
 * Ends the visit of the owner as an account of a family: the `web` guard signs out, the session key `visit` goes and
 * the log gets its line. Where the panel has its own host the session is the app's, and the owner was signed into it
 * only for the visit: the `owner` guard signs out too, so an owner is never signed in on the app outside a visit.
 * Without `ADMIN_DOMAIN` the session is also the panel's, and the owner stays signed into it.
 */
final readonly class EndVisit
{
    /**
     * @param  LogOwnerAction  $log  writes the line of the log
     */
    public function __construct(private LogOwnerAction $log) {}

    /**
     * Ends the visit kept in the session, if there is one.
     *
     * @param  Session  $session  the session of the request
     */
    public function handle(Session $session): void
    {
        $visit = $session->get('visit');

        if ($visit === null) {
            return;
        }

        // Only the current device: `logout()` would also cycle the remember token of the account, a write on it
        Auth::guard('web')->logoutCurrentDevice();
        if (SeparateHosts::active()) {
            Auth::guard('owner')->logoutCurrentDevice();
        }
        $session->forget('visit');
        // A new id, as after any change of who is signed in; the data (the owner of the panel) stays
        $session->migrate(true);

        if (! is_array($visit)) {
            return;
        }

        $user = is_int($visit['user_id'] ?? null) ? User::query()->find($visit['user_id']) : null;

        if (! $user instanceof User) {
            return;
        }

        $owner = is_int($visit['owner_id'] ?? null) ? Owner::query()->find($visit['owner_id']) : null;

        $this->log->handle(OwnerActionType::VisitEnded, $user->family, $user, $owner);
    }
}
