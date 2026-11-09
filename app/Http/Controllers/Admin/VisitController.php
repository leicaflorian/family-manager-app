<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\EndVisit;
use App\Actions\Admin\StartVisit;
use App\Http\Controllers\Controller;
use App\Http\Middleware\LimitVisit;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * *Enter as…*: the owner opens the app as an account of a family that gave the support permission, and leaves it.
 */
final class VisitController extends Controller
{
    /**
     * Starts the visit and opens the home of the app as the account. An owner who has not set up the authenticator
     * app yet is refused: the panel asks for it on every page, and this route is outside the panel.
     *
     * @param  Request  $request  the request
     * @param  User  $user  the account to look as
     * @param  Owner  $owner  the owner of the panel
     * @param  StartVisit  $start  starts the visit
     */
    public function start(Request $request, User $user, #[CurrentUser('owner')] Owner $owner, StartVisit $start): RedirectResponse
    {
        abort_if($owner->app_authentication_secret === null, 403);

        $start->handle($owner, $user, $request->session());

        return to_route('dashboard');
    }

    /**
     * Ends the visit and goes back to the panel.
     *
     * @param  Request  $request  the request
     * @param  EndVisit  $end  ends the visit
     */
    public function leave(Request $request, EndVisit $end): Response
    {
        abort_unless(LimitVisit::isOpen($request), 404);

        $end->handle($request->session());

        // The panel is not a page of Inertia: the browser has to load it whole
        return Inertia::location(route('filament.admin.pages.dashboard'));
    }
}
