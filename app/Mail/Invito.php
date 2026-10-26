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
 * L'email con il link per entrare nella famiglia.
 */
final class Invito extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  Invitation  $invito  l'invito da mandare
     */
    public function __construct(public readonly Invitation $invito) {}

    /**
     * L'oggetto dell'email.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ti aspettiamo nella famiglia su Family Manager');
    }

    /**
     * Il testo dell'email, con il link e fino a quando vale.
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.invito', with: [
            'chi' => $this->invito->creator->name ?? 'La tua famiglia',
            'link' => route('invito.show', $this->invito->token),
            'scade' => $this->invito->expires_at->format('d/m/Y'),
        ]);
    }
}
