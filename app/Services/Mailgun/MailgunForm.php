<?php

declare(strict_types=1);

namespace App\Services\Mailgun;

use App\Support\Mail\IncomingAttachment;
use App\Support\Mail\IncomingMail;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * The fields of a call of Mailgun (a forwarding rule or a route: the same fields) read as a mail, and the part
 * of them that is fit for the log.
 */
final class MailgunForm
{
    /** A text longer than this is kept in the log as its length. */
    private const int MAX_LOGGED_CHARS = 2000;

    /** The fields of Mailgun kept in the log as they are. */
    private const array LOGGED_FIELDS = ['recipient', 'sender', 'from', 'subject', 'attachment-count', 'timestamp'];

    /** The longest id of a mail, the length of the column `event_id`. */
    public const int MAX_EVENT_ID_CHARS = 255;

    /**
     * The headers of the mail, from the JSON string `message-headers`: a list of [name, value].
     *
     * @param  array<string, mixed>  $fields  the fields of the call
     * @return list<array{0: string, 1: string}> empty when the field cannot be read
     */
    public static function headersOf(array $fields): array
    {
        $raw = $fields['message-headers'] ?? '';
        $parsed = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($parsed)) {
            return [];
        }

        $pairs = [];
        foreach ($parsed as $pair) {
            if (is_array($pair) && array_is_list($pair) && count($pair) >= 2 && is_string($pair[0]) && is_string($pair[1])) {
                $pairs[] = [$pair[0], $pair[1]];
            }
        }

        return $pairs;
    }

    /**
     * From a call of Mailgun to a mail. The id is the same on every delivery of the same mail to the same address,
     * and so is the number of every attachment: that is how the files already saved are found. The address goes in
     * the id in lower case: a mail delivered again as Bills@… instead of bills@… is the same mail.
     *
     * @param  array<string, mixed>  $fields  the fields of the call
     * @param  array<string, mixed>  $files  the uploaded files, keyed by the name of their field
     * @return IncomingMail|null null without the address the mail was delivered to
     */
    public static function parse(array $fields, array $files): ?IncomingMail
    {
        $recipient = trim(self::text($fields, 'recipient'));
        if ($recipient === '') {
            return null;
        }
        $address = mb_strtolower($recipient);

        $headers = self::headersOf($fields);
        $from = self::text($fields, 'from');
        $subject = self::text($fields, 'subject');
        $date = self::headerValues($headers, $fields, 'Date')[0] ?? '';
        $messageId = trim(trim(self::headerValues($headers, $fields, 'Message-Id')[0] ?? ''), '<>');

        $copies = [...self::headerValues($headers, $fields, 'To'), ...self::headerValues($headers, $fields, 'Cc')];
        $recipients = array_values(array_unique([
            $address,
            ...array_map(mb_strtolower(...), self::addressesIn($copies)),
        ]));

        return new IncomingMail(
            // cut to the column: the files of the mail are keyed by the id as it is kept
            eventId: mb_substr($messageId !== ''
                ? "mailgun:{$messageId}:{$address}"
                : 'mailgun:'.hash('sha256', implode("\n", [$from, $address, $subject, $date])), 0, self::MAX_EVENT_ID_CHARS),
            sender: $from,
            senderAddress: self::addressOf($from),
            recipient: str_replace(' ', '', $address),
            recipients: $recipients,
            subject: $subject,
            date: self::dateOf($date),
            plainText: self::text($fields, 'body-plain'),
            html: self::text($fields, 'body-html'),
            attachments: self::attachmentsOf($files),
        );
    }

    /**
     * What is kept of a call in the log of the calls: a chosen list, not the body less something. Never the text
     * of the mail, the bytes of the files, `token` and `signature` (a valid signature for a day). Every header of
     * the mail comes twice, in `message-headers` and as a field of its own: they are left out. A text longer than
     * 2000 characters is kept as its length.
     *
     * @param  array<string, mixed>  $fields  the fields of the call
     * @param  list<string>  $headers  the names of the headers of the request
     * @return array<string, string|list<string>>
     */
    public static function redact(array $fields, array $headers): array
    {
        $kept = [];
        foreach (self::LOGGED_FIELDS as $name) {
            if (isset($fields[$name]) && is_string($fields[$name])) {
                $kept[$name] = self::clip($fields[$name]);
            }
        }

        return [...$kept, 'http-headers' => array_map(self::clip(...), $headers)];
    }

    /**
     * "Enel Energia <bollette@enel.it>" becomes "bollette@enel.it".
     */
    private static function addressOf(string $value): string
    {
        $bracketed = preg_match('/<([^<>]+)>\s*$/', $value, $match) === 1 ? $match[1] : $value;

        return mb_strtolower(trim($bracketed));
    }

    /**
     * Every address found in some headers: a name can hold a comma, so the values are searched, not split.
     *
     * @param  list<string>  $values
     * @return list<string>
     */
    private static function addressesIn(array $values): array
    {
        $found = [];
        foreach ($values as $value) {
            preg_match_all('/[^\s<>,;:"\'()\[\]]+@[^\s<>,;:"\'()\[\]]+/', $value, $matches);
            array_push($found, ...$matches[0]);
        }

        return $found;
    }

    /**
     * The values of a header, whatever the case of its name; when the list of headers has none, the field of the same name.
     *
     * @param  list<array{0: string, 1: string}>  $headers
     * @param  array<string, mixed>  $fields
     * @return list<string>
     */
    private static function headerValues(array $headers, array $fields, string $name): array
    {
        $found = [];
        foreach ($headers as [$header, $value]) {
            if (strcasecmp($header, $name) === 0) {
                $found[] = $value;
            }
        }
        if ($found !== []) {
            return $found;
        }

        return isset($fields[$name]) && is_string($fields[$name]) ? [$fields[$name]] : [];
    }

    /**
     * The `Date` header as a moment, null when it is empty or cannot be read.
     */
    private static function dateOf(string $value): ?CarbonImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The files of the fields `attachment-N`, in the order of N; any other file is left out. A file whose upload
     * failed is kept with the error of PHP, so its name and why it is missing are not lost.
     *
     * @param  array<string, mixed>  $files
     * @return list<IncomingAttachment>
     */
    private static function attachmentsOf(array $files): array
    {
        $numbered = [];
        foreach ($files as $name => $file) {
            if ($file instanceof UploadedFile && preg_match('/^attachment-(\d+)$/', (string) $name, $match) === 1) {
                $numbered[(int) $match[1]] = $file;
            }
        }
        ksort($numbered);

        $attachments = [];
        foreach ($numbered as $index => $file) {
            $attachments[] = new IncomingAttachment(
                filename: $file->getClientOriginalName(),
                contentType: $file->getClientMimeType(),
                size: (int) $file->getSize(),
                path: $file->getPathname(),
                index: $index,
                uploadError: $file->getError(),
            );
        }

        return $attachments;
    }

    /**
     * A field as text, empty when it is missing or not a text.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function text(array $fields, string $name): string
    {
        $value = $fields[$name] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * A text for the log: as it is, or its length when it is long.
     */
    private static function clip(string $value): string
    {
        $length = mb_strlen($value);

        return $length > self::MAX_LOGGED_CHARS ? "string({$length})" : $value;
    }
}
