<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invitation;
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
        return new Envelope(subject: __('Your family is waiting for you on Family Manager'));
    }

    /**
     * The text of the email, with the link and until when it is valid.
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.invitation', with: [
            'inviter' => $this->invitation->creator->name ?? __('Your family'),
            'link' => route('invitation.show', $this->invitation->token),
            'expiresOn' => $this->invitation->expires_at->translatedFormat(__('d/m/Y')),
        ]);
    }
}
