<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Invitation;
use App\Models\Person;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An invitation as whoever receives it sees it, on the *Invitation accepted* page.
 */
#[TypeScript]
final class InvitationData extends Data
{
    /**
     * @param  string  $first_name  the first name of the invited person, for the greeting
     * @param  string  $family  the name of the family
     * @param  string  $inviter  the first name of whoever sent the invitation
     * @param  string  $email  the email of the account being created
     * @param  string  $expires_on  until when it is valid, `Y-m-d`
     * @param  list<PersonSummaryData>  $people  the other people of the family, for the faces
     */
    public function __construct(
        public readonly string $first_name,
        public readonly string $family,
        public readonly string $inviter,
        public readonly string $email,
        public readonly string $expires_on,
        public readonly array $people,
    ) {}

    /**
     * The data of an invitation, read in its family.
     *
     * @param  Invitation  $invitation  the invitation, with its family set as the current one
     */
    public static function fromInvitation(Invitation $invitation): self
    {
        return new self(
            first_name: $invitation->person->first_name ?? (string) $invitation->first_name,
            family: $invitation->family->name,
            inviter: Str::before((string) $invitation->creator?->name, ' '),
            email: $invitation->email,
            expires_on: $invitation->expires_at->toDateString(),
            people: array_values(Person::query()->whereKeyNot($invitation->person_id)->orderBy('id')->get()
                ->map(PersonSummaryData::fromPerson(...))->all()),
        );
    }
}
