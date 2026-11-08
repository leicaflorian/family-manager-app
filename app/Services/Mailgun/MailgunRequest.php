<?php

declare(strict_types=1);

namespace App\Services\Mailgun;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * A call of Mailgun to `POST mail/mailgun` made by the app itself, signed as Mailgun signs it: the fields of a
 * mail, the files as `attachment-N`, and the signature over `timestamp` and `token`. It is how the app tries its
 * own route without Mailgun (`mail-inbox:try`), and the tests build their calls from the same pieces.
 */
final class MailgunRequest
{
    /** The route Mailgun calls. */
    public const string PATH = '/mail/mailgun';

    /** The content type of the call; the boundary is only for the look of it, the fields are not parsed from a body. */
    public const string CONTENT_TYPE = 'multipart/form-data; boundary=----mailgun';

    /**
     * A new `Message-Id`, the same on every delivery of the same mail.
     */
    public static function newMessageId(): string
    {
        return '<'.Str::random(20).'@mail.example>';
    }

    /**
     * The fields of a mail, without the signature.
     *
     * @param  string  $recipient  the address the mail is delivered to; empty leaves the field out
     * @param  string  $from  who wrote it
     * @param  string  $subject  the subject
     * @param  string  $text  the text part
     * @param  string  $html  the HTML part
     * @param  string  $messageId  the `Message-Id` header
     * @param  string  $toHeader  the `To` header
     * @param  int  $attachments  how many files come with it
     * @return array<string, string>
     */
    public static function mailFields(string $recipient, string $from, string $subject, string $text, string $html, string $messageId, string $toHeader, int $attachments): array
    {
        $headers = [
            ['Message-Id', $messageId],
            ['From', $from],
            ['To', $toHeader],
            ['Subject', $subject],
            ['Date', 'Sun, 25 Oct 2026 08:40:00 +0100'],
        ];

        $fields = [
            'recipient' => $recipient,
            'sender' => $from,
            'from' => $from,
            'subject' => $subject,
            'body-plain' => $text,
            'body-html' => $html,
            'message-headers' => (string) json_encode($headers),
            'attachment-count' => (string) $attachments,
        ];

        if ($recipient === '') {
            unset($fields['recipient']);
        }

        return $fields;
    }

    /**
     * The fields with the three of the signature added.
     *
     * @param  array<string, string>  $fields  the fields of the mail
     * @param  string  $key  the signing key
     * @param  int|null  $timestamp  the moment of the signature in seconds, now when null
     * @return array<string, string>
     */
    public static function sign(array $fields, string $key, ?int $timestamp = null): array
    {
        $timestamp = (string) ($timestamp ?? now()->getTimestamp());
        $token = Str::random(50);

        return $fields + ['timestamp' => $timestamp, 'token' => $token, 'signature' => hash_hmac('sha256', $timestamp.$token, $key)];
    }

    /**
     * A signed call to the route, ready to hand to `ReceiveMail` without a server.
     *
     * @param  string  $recipient  the address the mail is delivered to
     * @param  string  $from  who wrote it
     * @param  string  $subject  the subject
     * @param  string  $text  the text part
     * @param  list<array{name: string, path: string, mime: string}>  $files  the files to attach: the name, a local path and the type
     * @param  string  $key  the signing key
     */
    public static function make(string $recipient, string $from, string $subject, string $text, array $files, string $key): Request
    {
        $uploads = [];

        foreach ($files as $index => $file) {
            $uploads['attachment-'.($index + 1)] = new UploadedFile($file['path'], $file['name'], $file['mime'], null, true);
        }

        $fields = self::mailFields($recipient, $from, $subject, $text, '', self::newMessageId(), $recipient, count($files));

        return Request::create(self::PATH, 'POST', self::sign($fields, $key), [], $uploads, ['CONTENT_TYPE' => self::CONTENT_TYPE, 'HTTP_ACCEPT' => 'application/json']);
    }
}
