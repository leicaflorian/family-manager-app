<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Admin\EndVisit;
use App\Http\Middleware\LimitVisit;
use Illuminate\Auth\Events\Logout;

/**
 * The owner signs out of the panel during a visit: the visit ends there, with its line in the log. The sign-out of
 * Filament throws the session away right after, so {@see LimitVisit} would never see the visit to end it.
 */
final readonly class EndVisitOnOwnerLogout
{
    /**
     * @param  EndVisit  $end  ends the visit
     */
    public function __construct(private EndVisit $end) {}

    /**
     * Ends the visit kept in the session, if the owner is the one signing out.
     *
     * @param  Logout  $event  the sign-out
     */
    public function handle(Logout $event): void
    {
        if ($event->guard !== 'owner' || ! request()->hasSession()) {
            return;
        }

        $this->end->handle(request()->session());
    }
}
