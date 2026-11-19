<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Support\WelcomeLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The mail to the first account of a family created from the owner panel, with the link to choose the password
 * (board "Email-benvenuto" of the canvas).
 */
final class WelcomeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  User  $user  the account that chooses its password
     */
    public function __construct(public readonly User $user) {}

    /**
     * The subject of the email.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Welcome to :app', ['app' => config('app.name')]));
    }

    /**
     * The text of the email, with the link and how long it lasts.
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.welcome', with: [
            'name' => $this->user->firstName(),
            'family' => $this->user->family->name,
            'url' => WelcomeLink::for($this->user),
            'lasts' => trans_choice('{1} The link is valid for one day.|[2,*] The link is valid for :count days.', WelcomeLink::DAYS),
        ]);
    }
}
