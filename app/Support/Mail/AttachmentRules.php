<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Enums\SkipReason;

/**
 * Which attachments of an incoming mail are saved, and why the others are not.
 *
 * The type is read from the content, never from the declared type or the name:
 * senders attach PDFs as `application/octet-stream` and photos with the wrong
 * extension.
 */
final class AttachmentRules
{
    /** Images smaller than this are logos and signatures, not documents. */
    public const int MIN_IMAGE_BYTES = 30720;

    /** The most files of one mail that are read by the AI. */
    public const int MAX_READS_PER_MAIL = 5;

    /** The brands of the `ftyp` box that mean a HEIC photo. */
    private const array HEIC_BRANDS = ['heic', 'heix', 'mif1', 'msf1'];

    /**
     * The type the content really has, read from its first bytes.
     *
     * @param  string  $firstBytes  the start of the file (at least 12 bytes when there are that many)
     * @return 'pdf'|'jpg'|'png'|'heic'|null
     */
    public static function sniff(string $firstBytes): ?string
    {
        return match (true) {
            str_starts_with($firstBytes, '%PDF-') => 'pdf',
            str_starts_with($firstBytes, "\xff\xd8\xff") => 'jpg',
            str_starts_with($firstBytes, "\x89PNG\r\n\x1a\n") => 'png',
            substr($firstBytes, 4, 4) === 'ftyp' && in_array(substr($firstBytes, 8, 4), self::HEIC_BRANDS, true) => 'heic',
            default => null,
        };
    }

    /**
     * Why an attachment is not worth saving, or null when it is: a PDF or a photo within the size limits.
     *
     * @param  IncomingAttachment  $attachment  the file
     * @param  string  $firstBytes  the start of its content
     * @param  int  $maxBytes  the largest file that is saved
     */
    public static function verdict(IncomingAttachment $attachment, string $firstBytes, int $maxBytes): ?SkipReason
    {
        return match (true) {
            // bigger than the upload limit of PHP: nothing of it arrived, but it is a file too large
            in_array($attachment->uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) => SkipReason::TooLarge,
            self::sniff($firstBytes) === null => SkipReason::NotADocument,
            $attachment->size > $maxBytes => SkipReason::TooLarge,
            self::sniff($firstBytes) !== 'pdf' && $attachment->size < self::MIN_IMAGE_BYTES => SkipReason::TooSmall,
            default => null,
        };
    }

    /**
     * What tells two entries of the same file apart from two files: name, declared type and size. A mail can
     * list one file more than once.
     *
     * @return ?string null when the size is not known: nothing is said to be a copy then
     */
    public static function sameFileKey(IncomingAttachment $attachment): ?string
    {
        return $attachment->size > 0
            ? implode("\n", [$attachment->filename, strtolower($attachment->contentType), $attachment->size])
            : null;
    }

    /**
     * The key of a file of a mail, always the same for the same mail and position: a delivery repeated by
     * Mailgun finds its files already there.
     *
     * @param  string  $eventId  the id of the delivery
     * @param  int  $index  the number of the attachment, -1 for the notice
     */
    public static function mailKey(string $eventId, int $index): string
    {
        return hash('sha256', "mail-inbox:{$eventId}:{$index}");
    }

    /**
     * The attachment's own name without folders or control characters, with the extension of its real type.
     *
     * @param  string  $filename  the name the sender gave the file
     * @param  string  $extension  the extension of the real type, without the dot
     */
    public static function fileName(string $filename, string $extension): string
    {
        $parts = preg_split('~[\\\\/]~', $filename);
        $last = is_array($parts) ? (string) end($parts) : '';
        $base = preg_replace('~[\x00-\x1f]~', ' ', $last) ?? '';
        $base = preg_replace('~\.[a-z0-9]+$~i', '', $base) ?? '';
        $base = preg_replace('~^[.\s]+~u', '', $base) ?? '';
        $base = mb_substr(trim($base), 0, 150);

        return ($base === '' ? 'allegato' : $base).'.'.$extension;
    }

    /**
     * The ids of the files to read: the PDFs, and only when there are none the photos; at most
     * MAX_READS_PER_MAIL, in the order given.
     *
     * @param  list<array{id: int, type: string}>  $saved  the files saved from the mail, with their MIME type
     * @return list<int>
     */
    public static function toRead(array $saved): array
    {
        $pdfs = array_filter($saved, static fn (array $file): bool => $file['type'] === 'application/pdf');
        $chosen = $pdfs !== [] ? $pdfs : array_filter($saved, static fn (array $file): bool => str_starts_with($file['type'], 'image/'));

        return array_map(
            static fn (array $file): int => $file['id'],
            array_slice(array_values($chosen), 0, self::MAX_READS_PER_MAIL),
        );
    }
}
