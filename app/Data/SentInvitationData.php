<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Invitation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An invitation just sent, with the link to copy. During a visit of the owner the link is hidden: it would let the
 * owner join the family.
 */
#[TypeScript]
final class SentInvitationData extends Data
{
    /**
     * @param  string  $email  who it went to
     * @param  string|null  $link  the link to join; null during a visit
     * @param  string  $expires_on  until when it is valid, `Y-m-d`
     */
    public function __construct(
        public readonly string $email,
        public readonly ?string $link,
        public readonly string $expires_on,
    ) {}

    /**
     * The data of a sent invitation.
     *
     * @param  Invitation  $invitation  the invitation
     * @param  bool  $hideLink  whether the link stays out of the page (during a visit of the owner)
     */
    public static function fromInvitation(Invitation $invitation, bool $hideLink = false): self
    {
        return new self(
            $invitation->email,
            $hideLink ? null : route('invitation.show', $invitation->token),
            $invitation->expires_at->toDateString(),
        );
    }
}
