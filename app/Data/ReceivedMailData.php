<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\SkipReason;
use App\Models\File;
use App\Models\ReceivedMail;
use App\Support\Mail\MailFlow;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A received mail open in *Received mail*: its row, who wrote and to whom, what was done with each file, the files
 * left out, the notes, the code of Gmail, what is kept of its text and what whoever signed in may do with it.
 */
#[TypeScript]
final class ReceivedMailData extends Data
{
    /**
     * @param  ReceivedMailRowData  $row  the row of the mail in the list
     * @param  string  $from  the sender as the header wrote it, with the name
     * @param  string  $recipient  the address the mail was delivered to
     * @param  list<string>  $recipients  the addresses of `To` and `Cc`
     * @param  list<ReceivedMailFileData>  $files  the files it saved, with where they are now
     * @param  list<array{name: string, reason: string}>  $skipped  the attachments left out, with why in words
     * @param  list<string>  $notes  what the work after the answer wrote («scartata: offerta commerciale»)
     * @param  string|null  $gmail_code  the code of the confirmation of Gmail
     * @param  bool  $has_text  whether a text of the mail is kept
     * @param  bool  $has_both_texts  whether both the plain text and the HTML are kept, to switch between them
     * @param  int|null  $html_too_large  the size in bytes of an HTML too large to keep, null when it was kept or there was none
     * @param  string  $text_url  the address of the text, shown isolated in a frame
     * @param  bool  $can_save_as_notice  whether whoever signed in may save the mail as a notice, and the mail allows it
     * @param  bool  $can_delete  whether whoever signed in may delete the mail
     * @param  string|null  $pending_notice  the name of the notice deleted with the mail: still in *To sort* and not a document
     */
    public function __construct(
        public readonly ReceivedMailRowData $row,
        public readonly string $from,
        public readonly string $recipient,
        public readonly array $recipients,
        public readonly array $files,
        #[LiteralTypeScriptType('Array<{ name: string; reason: string }>')]
        public readonly array $skipped,
        public readonly array $notes,
        public readonly ?string $gmail_code,
        public readonly bool $has_text,
        public readonly bool $has_both_texts,
        public readonly ?int $html_too_large,
        public readonly string $text_url,
        public readonly bool $can_save_as_notice,
        public readonly bool $can_delete,
        public readonly ?string $pending_notice,
    ) {}

    /**
     * The data of a mail open.
     *
     * @param  ReceivedMail  $mail  the mail
     */
    public static function fromMail(ReceivedMail $mail): self
    {
        $mail->loadMissing(['files.document', 'organization']);
        $entries = $mail->savedEntries();
        $body = $mail->body ?? [];
        $plain = is_string($body['plainText'] ?? null) && $body['plainText'] !== '';
        $html = is_string($body['html'] ?? null) && $body['html'] !== '';
        $notice = $mail->files->first(static fn (File $file): bool => $file->mail_key === $mail->noticeKey());

        return new self(
            row: ReceivedMailRowData::fromMail($mail),
            from: $mail->sender,
            recipient: $mail->recipient,
            recipients: array_values(array_filter($mail->recipients, is_string(...))),
            files: array_map(static fn (array $entry): ReceivedMailFileData => ReceivedMailFileData::fromSaved($entry['name'], $entry['file']), $entries),
            skipped: self::skipped($mail->skipped),
            notes: array_values(array_filter($mail->notes, is_string(...))),
            gmail_code: $mail->gmail_code,
            has_text: $plain || $html,
            has_both_texts: $plain && $html,
            html_too_large: is_int($body['htmlBytes'] ?? null) ? $body['htmlBytes'] : null,
            text_url: route('mail.text', $mail),
            can_save_as_notice: Gate::allows('update', $mail) && MailFlow::canSaveAsNotice($mail->outcome, count($entries)),
            can_delete: Gate::allows('delete', $mail),
            pending_notice: $notice instanceof File && $notice->to_sort && $notice->document === null ? $notice->original_name : null,
        );
    }

    /**
     * The attachments left out, with the reason in words.
     *
     * @param  array<array-key, mixed>  $skipped  the column `skipped`
     * @return list<array{name: string, reason: string}>
     */
    private static function skipped(array $skipped): array
    {
        $rows = [];

        foreach ($skipped as $entry) {
            if (is_array($entry) && is_string($entry['name'] ?? null)) {
                $reason = is_string($entry['reason'] ?? null) ? SkipReason::tryFrom($entry['reason']) : null;
                $rows[] = ['name' => $entry['name'], 'reason' => $reason?->label() ?? ''];
            }
        }

        return $rows;
    }
}
