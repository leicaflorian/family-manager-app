<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Invitations\AcceptInvitation;
use App\Data\InvitationData;
use App\Http\Requests\Family\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Support\CurrentFamily;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The invitation link, opened by whoever receives it: no need to be signed in.
 */
final class InvitationLinkController extends Controller
{
    /**
     * *Invitation accepted*, or *This invitation is no longer valid* if it expired,
     * was already used or made up.
     *
     * @param  string  $token  the token of the link
     */
    public function show(string $token): Response
    {
        $invitation = Invitation::findValid($token);

        if (! $invitation instanceof Invitation) {
            return Inertia::render('auth/InvalidInvitation');
        }

        return Inertia::render('auth/AcceptInvitation', [
            'token' => $token,
            'invitation' => CurrentFamily::using($invitation->family_id, static fn (): InvitationData => InvitationData::fromInvitation($invitation)),
        ]);
    }

    /**
     * Creates the account, signs in and goes to the Home. An invalid invitation gives 404.
     *
     * @param  string  $token  the token of the link
     */
    public function store(AcceptInvitationRequest $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = Invitation::findValid($token);
        abort_unless($invitation instanceof Invitation, 404);

        Auth::login($acceptInvitation->handle($invitation, $request->string('password')->toString()));
        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
