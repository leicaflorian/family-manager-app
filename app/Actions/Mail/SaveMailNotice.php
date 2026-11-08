<?php

declare(strict_types=1);

namespace App\Actions\Mail;

use App\Enums\FileSource;
use App\Models\File;
use App\Models\ReceivedMail;
use App\Services\FileStore;
use App\Support\Mail\IncomingMail;
use App\Support\Mail\NoticeText;
use RuntimeException;
use Throwable;

/**
 * The notice of a mail without attachments: its text saved in *To sort* as `Notice - <subject>.txt`, with the key
 * of the position -1. Saved by the job after the screening, or by hand with *Salva come avviso*.
 */
final readonly class SaveMailNotice
{
    /**
     * @param  FileStore  $files  where the notice is saved
     */
    public function __construct(private FileStore $files) {}

    /**
     * The notice of the mail, when it is saved already (it may be a document by now).
     *
     * @param  ReceivedMail  $mail  the mail
     */
    public function find(ReceivedMail $mail): ?File
    {
        return File::query()->where('mail_key', $mail->noticeKey())->first();
    }

    /**
     * Saves the notice, and writes it among the files the mail saved.
     *
     * @param  ReceivedMail  $mail  the mail
     * @return File|null the notice, null when the mail kept no text to save
     *
     * @throws RuntimeException if the file is not written
     * @throws Throwable if its row is not written
     */
    public function handle(ReceivedMail $mail): ?File
    {
        $body = $mail->body ?? [];
        $plain = is_string($body['plainText'] ?? null) ? $body['plainText'] : '';
        $html = is_string($body['html'] ?? null) ? $body['html'] : '';
        $text = NoticeText::textOf($plain, $html);

        if ($text === null) {
            return null;
        }

        $subject = (string) $mail->subject;
        $incoming = new IncomingMail($mail->event_id, $mail->sender, $mail->sender_address, $mail->recipient, [], $subject, null, $plain, $html, []);

        $notice = $this->files->storeContents(
            NoticeText::file($incoming, $mail->received_at, $text),
            NoticeText::name($subject),
            'text/plain',
            true,
            FileSource::Mail,
            NoticeText::note($mail->sender_address, $mail->subject, $mail->received_at),
            ['received_mail_id' => $mail->id, 'mail_key' => $mail->noticeKey()],
        );
        $mail->recordSaved($notice);

        return $notice;
    }
}
