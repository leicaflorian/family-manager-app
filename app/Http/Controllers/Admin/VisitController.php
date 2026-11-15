<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\EndVisit;
use App\Actions\Admin\StartVisit;
use App\Http\Controllers\Controller;
use App\Http\Middleware\LimitVisit;
use App\Models\Owner;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Support\VisitHandoff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * *Enter as…*: the owner opens the app as an account of a family that gave the support permission, and leaves it.
 */
final class VisitController extends Controller
{
    /**
     * Starts the visit from the one-time link of the panel and opens the home of the app as the account. The token of
     * the link gives the owner who made it, who is signed into this session too ({@see LimitVisit} checks it on every
     * request). An owner who has not set up the authenticator app yet is refused where the panel requires it
     * (everywhere but in the local environment): the panel asks for it on every page, and this route is outside it.
     *
     * @param  Request  $request  the request
     * @param  User  $user  the account to look as
     * @param  StartVisit  $start  starts the visit
     */
    public function start(Request $request, User $user, StartVisit $start): RedirectResponse
    {
        $owner = VisitHandoff::consume($request->string('token')->toString(), $user->id);

        abort_if(! $owner instanceof Owner || (AdminPanelProvider::requiresAuthenticator() && $owner->app_authentication_secret === null), 403);

        $start->handle($owner, $user, $request->session());
        Auth::guard('owner')->login($owner);

        return to_route('dashboard');
    }

    /**
     * Ends the visit, signs the owner out of this session (the panel keeps its own) and goes back to the panel.
     *
     * @param  Request  $request  the request
     * @param  EndVisit  $end  ends the visit
     */
    public function leave(Request $request, EndVisit $end): Response
    {
        abort_unless(LimitVisit::isOpen($request), 404);

        $end->handle($request->session());
        // Only this device, and without the event `Logout`: the visit is already over and logged
        Auth::guard('owner')->logoutCurrentDevice();

        // The panel is not a page of Inertia: the browser has to load it whole
        return Inertia::location(route('filament.admin.pages.dashboard'));
    }
}
