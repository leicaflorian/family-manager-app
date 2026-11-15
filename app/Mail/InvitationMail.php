<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invitation;
use App\Models\Person;
use App\Support\CurrentFamily;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email with the link to join the family.
 */
final class InvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  Invitation  $invitation  the invitation to send
     */
    public function __construct(public readonly Invitation $invitation) {}

    /**
     * The subject of the email.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: __(':inviter invites you to the :family family', [
            'inviter' => $this->inviter(),
            'family' => $this->invitation->family->name,
        ]));
    }

    /**
     * The text of the email, with the link and until when it is valid.
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.invitation', with: [
            'inviter' => $this->inviter(),
            // Nobody is signed in to the family when the queue sends it, so the people are read inside it
            'initials' => CurrentFamily::using($this->invitation->family_id, fn (): array => Person::query()->orderBy('id')->limit(5)->pluck('initials')->all()),
            'link' => route('invitation.show', $this->invitation->token),
            'expiresOn' => $this->invitation->expires_at->translatedFormat(__('d/m/Y')),
        ]);
    }

    /**
     * The name of who sent the invitation, or the family when that account is gone.
     */
    private function inviter(): string
    {
        return $this->invitation->creator->name ?? __('Your family');
    }
}
