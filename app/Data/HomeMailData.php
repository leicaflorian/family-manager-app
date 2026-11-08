<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Document;
use App\Models\ReceivedMail;
use App\Services\DocumentLinks;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A mail in the box *Arrived by mail* of the Home: who wrote, the subject, when it arrived and the cards its documents
 * are linked to.
 */
#[TypeScript]
final class HomeMailData extends Data
{
    /**
     * @param  int  $id  the id of the mail
     * @param  string  $sender  who wrote: the organization of a known sender, the name in the header or the address
     * @param  string|null  $subject  the subject, null when the mail had none
     * @param  string  $received_at  when it arrived first, ISO with the offset of Rome
     * @param  list<string>  $links  the names of the cards the documents born from the mail are linked to, each once
     */
    public function __construct(
        public readonly int $id,
        public readonly string $sender,
        public readonly ?string $subject,
        public readonly string $received_at,
        public readonly array $links,
    ) {}

    /**
     * The box row of a mail.
     *
     * @param  ReceivedMail  $mail  the mail, with its organization loaded
     * @param  list<Document>  $documents  the documents born from its files, with their cards loaded
     */
    public static function fromMail(ReceivedMail $mail, array $documents): self
    {
        $links = [];

        foreach ($documents as $document) {
            foreach (DocumentLinks::loadedLabels($document) as $label) {
                $links[$label] = $label;
            }
        }

        return new self(
            id: $mail->id,
            sender: $mail->senderName(),
            subject: $mail->subject,
            received_at: $mail->received_at->setTimezone('Europe/Rome')->toIso8601String(),
            links: array_values($links),
        );
    }
}
