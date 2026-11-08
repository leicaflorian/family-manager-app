<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Support\Money;
use App\Support\Translated;
use Carbon\CarbonImmutable;

/**
 * The notice of a mail that has no file: the text of the mail kept as a plain text file, and the lines the
 * notification says about what was read. Nothing here has a person to write for, so the text is in the language
 * of the installation (`APP_LOCALE`).
 */
final class NoticeText
{
    /** A plain text shorter than this is taken for a placeholder ("open this mail in a browser"): the HTML is used. */
    public const int MIN_PLAIN_CHARS = 80;

    public const int MAX_CHARS = 20_000;

    /** The named entities that are decoded; any other is left as written. */
    private const array ENTITIES = ['nbsp' => ' ', 'amp' => '&', 'lt' => '<', 'gt' => '>', 'quot' => '"', 'euro' => '€'];

    /**
     * From the text parts of a mail to the text of the notice.
     *
     * The plain text when it has at least MIN_PLAIN_CHARS characters, otherwise the text of the HTML; cut at
     * MAX_CHARS and marked.
     *
     * @param  string|null  $plain  the text part
     * @param  string|null  $html  the HTML part
     * @return string|null the text, or null when both parts are empty
     */
    public static function textOf(?string $plain, ?string $html): ?string
    {
        $plainText = self::clean($plain ?? '');
        $htmlText = self::clean(self::fromHtml($html ?? ''));
        $text = mb_strlen($plainText) >= self::MIN_PLAIN_CHARS || $htmlText === '' ? $plainText : $htmlText;

        if ($text === '') {
            return null;
        }

        return mb_strlen($text) > self::MAX_CHARS ? self::cut($text, self::MAX_CHARS) : $text;
    }

    /**
     * The text cut at a number of characters, with the end trimmed and the marker after it.
     *
     * @param  string  $text  the text
     * @param  int  $max  the characters to keep
     */
    public static function cut(string $text, int $max): string
    {
        return rtrim(mb_substr($text, 0, $max))."\n".self::translate('[text cut]');
    }

    /**
     * The name of the file: "Notice - <subject>.txt", without the forward and reply prefixes and without
     * characters a file name cannot have.
     *
     * @param  string  $subject  the subject of the mail
     */
    public static function name(string $subject): string
    {
        $base = $subject;

        do {
            $previous = $base;
            $base = (string) preg_replace('/^\s*(?:fwd|fw|i|inoltro|r|re)\s*:\s*/iu', '', $base);
        } while ($previous !== $base);

        $base = (string) preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]/u', '-', $base);
        $base = self::trim(mb_substr(self::trim($base), 0, 150));

        return self::translate('Notice - :subject.txt', ['subject' => $base === '' ? self::translate('no subject') : $base]);
    }

    /**
     * The content of the file: UTF-8 with a byte order mark, the two fixed lines, who wrote it, the subject, when
     * it arrived (hour of Rome) and the text.
     *
     * @param  IncomingMail  $mail  the mail
     * @param  CarbonImmutable  $receivedAt  when it arrived
     * @param  string  $text  the text of the mail
     */
    public static function file(IncomingMail $mail, CarbonImmutable $receivedAt, string $text): string
    {
        return "\u{FEFF}".implode("\n", [
            self::translate('Notice that came by mail, with no attachment.'),
            self::translate("To upload the real invoice you need to sign in to the supplier's private area."),
            '',
            self::translate('From: :sender', ['sender' => $mail->senderAddress]),
            self::translate('Subject: :subject', ['subject' => $mail->subject === '' ? self::translate('no subject') : $mail->subject]),
            self::translate('Arrived on: :date', ['date' => $receivedAt->setTimezone('Europe/Rome')->format('d/m/Y H:i')]),
            '',
            $text,
        ]);
    }

    /**
     * What a reading says, for the notification: "Edison, 84,20 €, scade il 30/10/2026".
     *
     * @param  string|null  $issuer  who sent the document
     * @param  float|null  $amount  the total in euro
     * @param  string|null  $dueDate  the due date, `yyyy-mm-dd`
     * @return string|null the line, or null when the reading says nothing
     */
    public static function readingLine(?string $issuer, ?float $amount, ?string $dueDate): ?string
    {
        $parts = [];

        if ($issuer !== null && $issuer !== '') {
            $parts[] = $issuer;
        }

        if ($amount !== null && is_finite($amount)) {
            $parts[] = Money::euro($amount);
        }

        if ($dueDate !== null && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dueDate, $date) === 1) {
            $parts[] = self::translate('due on :date', ['date' => "{$date[3]}/{$date[2]}/{$date[1]}"]);
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Whether to send the notification: not when nothing was saved, and not for a notice when only mails with
     * attachments are wanted.
     *
     * @param  bool  $savedSomething  whether a file was saved
     * @param  bool  $isNotice  whether the only file is the notice text
     * @param  bool  $attachmentsOnly  whether the family wants only mails with attachments
     */
    public static function shouldNotify(bool $savedSomething, bool $isNotice, bool $attachmentsOnly): bool
    {
        return $savedSomething && (! $isNotice || ! $attachmentsOnly);
    }

    /**
     * A text in the language of the installation.
     *
     * @param  string  $key  the English text
     * @param  array<string, string|int>  $replace  the values of the placeholders
     */
    public static function translate(string $key, array $replace = []): string
    {
        return Translated::text($key, $replace, config()->string('app.locale'));
    }

    /**
     * The text of an HTML part: no head, style, script or comments, one line per block, entities decoded.
     *
     * @param  string  $html  the HTML
     */
    private static function fromHtml(string $html): string
    {
        $text = (string) preg_replace('/<!--.*?-->/su', '', $html);
        $text = (string) preg_replace('/<(head|style|script)\b.*?<\/\1\s*>/isu', '', $text);
        $text = (string) preg_replace('/<br\s*\/?>|<\/(?:p|div|tr|li|h[1-6])\s*>/iu', "\n", $text);
        $text = (string) preg_replace('/<\/td\s*>/iu', ' ', $text);
        $text = (string) preg_replace('/<[^>]*>/u', '', $text);

        return (string) preg_replace_callback('/&(#x[0-9a-f]+|#\d+|[a-z]+);/iu', self::decodeEntity(...), $text);
    }

    /**
     * One entity as the character it stands for, or as written when it is unknown or out of range.
     *
     * @param  array<int, string>  $match  the match and its entity
     */
    private static function decodeEntity(array $match): string
    {
        $entity = $match[1];

        if ($entity[0] === '#') {
            // hexdec() gives a float past the integer range, so the value is compared as a number before any cast.
            $code = strtolower($entity[1]) === 'x' ? hexdec(substr($entity, 2)) : (float) substr($entity, 1);
            $char = $code > 0 && $code <= 0x10FFFF ? mb_chr((int) $code, 'UTF-8') : false;

            return $char === false ? $match[0] : $char;
        }

        return self::ENTITIES[strtolower($entity)] ?? $match[0];
    }

    /**
     * Line ends as `\n`, runs of blanks as one space, at most one empty line in a row, no blanks around the text.
     *
     * @param  string  $value  the text
     */
    private static function clean(string $value): string
    {
        $value = (string) preg_replace('/\r\n?/', "\n", $value);
        $value = (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $value);
        $value = (string) preg_replace('/ ?\n ?/', "\n", $value);
        $value = (string) preg_replace('/\n{3,}/', "\n\n", $value);

        return self::trim($value);
    }

    /**
     * The text without whitespace at both ends, non-breaking space included.
     *
     * @param  string  $value  the text
     */
    private static function trim(string $value): string
    {
        return (string) preg_replace('/^\s+|\s+$/u', '', $value);
    }
}
