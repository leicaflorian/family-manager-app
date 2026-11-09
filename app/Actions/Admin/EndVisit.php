<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\OwnerActionType;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

/**
 * Ends the visit of the owner as an account of a family: the `web` guard signs out, the session key `visit` goes and
 * the log gets its line. The session stays, so the owner is still signed into the panel.
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
