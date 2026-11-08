<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Carbon\CarbonImmutable;

/**
 * A mail as one call of Mailgun describes it, before anything is decided about it.
 */
final readonly class IncomingMail
{
    /**
     * @param  string  $eventId  the same on every delivery of the same mail to the same address
     * @param  string  $sender  the `from` as it is written, name included
     * @param  string  $senderAddress  the address inside `sender`, in lower case
     * @param  string  $recipient  the address the mail was delivered to, in lower case
     * @param  list<string>  $recipients  every address of `To` and `Cc`, plus the recipient, in lower case
     * @param  string  $subject  the subject
     * @param  CarbonImmutable|null  $date  the `Date` header, null when it is missing or cannot be read
     * @param  string  $plainText  the text part
     * @param  string  $html  the HTML part
     * @param  list<IncomingAttachment>  $attachments  the files, by their number
     */
    public function __construct(
        public string $eventId,
        public string $sender,
        public string $senderAddress,
        public string $recipient,
        public array $recipients,
        public string $subject,
        public ?CarbonImmutable $date,
        public string $plainText,
        public string $html,
        public array $attachments,
    ) {}
}
