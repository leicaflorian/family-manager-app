<?php

declare(strict_types=1);

namespace App\Support\Mail;

/**
 * What is kept of the text of a mail: the plain text cut at a limit, the HTML whole or not at all.
 */
final class StoredBody
{
    public const int MAX_TEXT_CHARS = 200_000;

    public const int MAX_HTML_BYTES = 1_048_576;

    /**
     * From the text parts of a mail to what is saved. The plain text is cut as the notice is; the HTML is kept
     * whole or not at all, because cut in the middle it would show broken.
     *
     * @param  string|null  $plain  the text part
     * @param  string|null  $html  the HTML part
     * @return array{plainText: ?string, html: ?string, htmlBytes?: int}|null null when nothing is left
     */
    public static function of(?string $plain, ?string $html): ?array
    {
        $plainText = $plain ?? '';
        $htmlText = $html ?? '';

        if (mb_strlen($plainText) > self::MAX_TEXT_CHARS) {
            $plainText = NoticeText::cut($plainText, self::MAX_TEXT_CHARS);
        }

        $htmlBytes = strlen($htmlText);

        if ($htmlBytes > self::MAX_HTML_BYTES) {
            return ['plainText' => $plainText === '' ? null : $plainText, 'html' => null, 'htmlBytes' => $htmlBytes];
        }

        if ($plainText === '' && $htmlText === '') {
            return null;
        }

        return ['plainText' => $plainText === '' ? null : $plainText, 'html' => $htmlText === '' ? null : $htmlText];
    }
}
