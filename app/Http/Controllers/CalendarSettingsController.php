<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Calendar\ResetCalendarLink;
use App\Actions\Calendar\TurnOffCalendar;
use App\Actions\Calendar\TurnOnCalendar;
use App\Data\CalendarSettingsData;
use App\Http\Middleware\LimitVisit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Settings → Calendar*: the family turns its secret calendar address on and off, and changes it. Everyone sees the
 * address; only an administrator changes it (checked by the policy).
 */
final class CalendarSettingsController extends Controller
{
    /**
     * The page; without the address during a visit of the owner.
     *
     * @param  Request  $request  the request
     * @param  User  $user  whoever signed in
     */
    public function edit(Request $request, #[CurrentUser] User $user): Response
    {
        return Inertia::render('settings/Calendar', [
            'calendar' => CalendarSettingsData::fromFamily($user->family, $user, LimitVisit::isOpen($request)),
        ]);
    }

    /**
     * Turns the calendar on, in the language of the page. Already on, nothing changes: the address in use goes on.
     *
     * @param  User  $user  whoever signed in
     * @param  TurnOnCalendar  $turnOn  the action
     */
    public function store(#[CurrentUser] User $user, TurnOnCalendar $turnOn): RedirectResponse
    {
        Gate::authorize('manageCalendar', $user->family);

        if ($user->family->calendar_token_hash === null) {
            $turnOn->handle($user->family, app()->getLocale());
        }

        return to_route('calendar.edit');
    }

    /**
     * Turns the calendar off: the address stops working.
     *
     * @param  User  $user  whoever signed in
     * @param  TurnOffCalendar  $turnOff  the action
     */
    public function destroy(#[CurrentUser] User $user, TurnOffCalendar $turnOff): RedirectResponse
    {
        Gate::authorize('manageCalendar', $user->family);

        $turnOff->handle($user->family);

        return to_route('calendar.edit');
    }

    /**
     * Changes the address: the old one stops working. Only for a calendar that is on.
     *
     * @param  User  $user  whoever signed in
     * @param  ResetCalendarLink  $reset  the action
     */
    public function reset(#[CurrentUser] User $user, ResetCalendarLink $reset): RedirectResponse
    {
        Gate::authorize('manageCalendar', $user->family);
        abort_if($user->family->calendar_token_hash === null, 404);

        $reset->handle($user->family);

        return to_route('calendar.edit');
    }
}
