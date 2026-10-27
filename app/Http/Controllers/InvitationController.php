<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Invitations\SendInvitation;
use App\Data\OptionData;
use App\Data\PersonSummaryData;
use App\Data\SentInvitationData;
use App\Enums\PersonRole;
use App\Enums\Role;
use App\Http\Requests\Family\SendInvitationRequest;
use App\Models\Invitation;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Invite a family member*: the form, the sending and the page with the link to
 * copy. Only an `admin` invites.
 */
final class InvitationController extends Controller
{
    /**
     * The form: who it is is chosen among the people without an account, or is a new person.
     */
    public function create(): Response
    {
        Gate::authorize('create', Invitation::class);

        return Inertia::render('family/Invite', [
            'people' => Person::query()->whereNull('user_id')->orderBy('first_name')->get()
                ->map(PersonSummaryData::fromPerson(...))->values()->all(),
            'levels' => OptionData::list(Role::options()),
            'roles' => OptionData::list(PersonRole::options()),
        ]);
    }

    /**
     * Creates the invitation, sends the email and goes to the page with the link.
     */
    public function store(SendInvitationRequest $request, SendInvitation $sendInvitation): RedirectResponse
    {
        /** @var array{email: string, role: string, person_id?: int|null, first_name?: string|null, last_name?: string|null, person_role?: string|null} $data */
        $data = $request->validated();

        return to_route('family.invitations.show', $sendInvitation->handle($data));
    }

    /**
     * The invitation just sent, with the link to copy if the email does not arrive.
     */
    public function show(Invitation $invitation): Response
    {
        Gate::authorize('create', Invitation::class);

        return Inertia::render('family/InvitationSent', [
            'invitation' => SentInvitationData::fromInvitation($invitation),
        ]);
    }
}
