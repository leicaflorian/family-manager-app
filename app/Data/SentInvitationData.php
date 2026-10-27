<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Invitation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An invitation just sent, with the link to copy.
 */
#[TypeScript]
final class SentInvitationData extends Data
{
    /**
     * @param  string  $email  who it went to
     * @param  string  $link  the link to join
     * @param  string  $expires_on  until when it is valid, `Y-m-d`
     */
    public function __construct(
        public readonly string $email,
        public readonly string $link,
        public readonly string $expires_on,
    ) {}

    /**
     * The data of a sent invitation.
     *
     * @param  Invitation  $invitation  the invitation
     */
    public static function fromInvitation(Invitation $invitation): self
    {
        return new self($invitation->email, route('invitation.show', $invitation->token), $invitation->expires_at->toDateString());
    }
}
