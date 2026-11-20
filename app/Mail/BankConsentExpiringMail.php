<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Support\AppHostUrl;
use App\Support\Translated;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email to an `admin` when the consent of a bank is about to end, or the bank took it back, with the button to
 * connect the bank again.
 */
final class BankConsentExpiringMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  User  $user  who gets the email
     * @param  string  $bank  the name of the bank
     * @param  string  $validUntil  the day the consent ends, `dd/mm/yyyy` (plain values: the queue has no family to read a connection from)
     * @param  bool  $ended  true when the bank took the consent back
     */
    public function __construct(public readonly User $user, public readonly string $bank, public readonly string $validUntil, public readonly bool $ended) {}

    /**
     * The subject of the email.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Connect :bank again', ['bank' => $this->bank]));
    }

    /**
     * The text of the email, with the button to the banks page on the host of the app.
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.bank-consent-expiring', with: [
            'name' => $this->user->firstName(),
            'text' => $this->sentence(),
            'url' => AppHostUrl::generator()->route('banks.index'),
        ]);
    }

    /**
     * What happened to the consent, in a language.
     *
     * @param  string|null  $locale  the language, the current one when null
     */
    public function sentence(?string $locale = null): string
    {
        return $this->ended
            ? Translated::text(':bank has removed the link. Connect it again to keep receiving the transactions.', ['bank' => $this->bank], $locale)
            : Translated::text('The link with :bank ends on :date. Connect it again to keep receiving the transactions.', ['bank' => $this->bank, 'date' => $this->validUntil], $locale);
    }
}
