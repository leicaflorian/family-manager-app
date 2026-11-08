<?php

declare(strict_types=1);

namespace App\Support\Mail;

/**
 * The page the text of a received mail is shown with, inside a frame (`previewDocument` of Directus). The HTML goes
 * in as it came, not cleaned: scripts and requests are stopped by the browser, with the sandbox of the frame and the
 * policy of the page.
 */
final class MailText
{
    /**
     * Written in the answer and inside the page, before anything of the mail: no request leaves the page (images,
     * style sheets, fonts), only the styles written in the mail stay.
     */
    public const string POLICY = "default-src 'none'; style-src 'unsafe-inline'; img-src data:; form-action 'none'; base-uri 'none'";

    /** White with dark text whatever the theme of the app: mails are made for a white page. */
    private const string STYLE = 'html { background: #fff; color: #1f2933; color-scheme: light; } '
        .'body { margin: 16px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; overflow-wrap: anywhere; } '
        .'pre.plain { margin: 0; font: inherit; white-space: pre-wrap; }';

    /**
     * The whole page for the frame: the HTML of the mail when there is one and the plain text is not asked,
     * otherwise its plain text. Every link opens in a new tab (`base`).
     *
     * @param  array<array-key, mixed>  $body  the column `body` of the mail
     * @param  bool  $plain  true to show the plain text even when there is HTML
     * @return string|null the page, null when no text was kept
     */
    public static function document(array $body, bool $plain): ?string
    {
        $text = is_string($body['plainText'] ?? null) ? $body['plainText'] : '';
        $html = is_string($body['html'] ?? null) ? $body['html'] : '';

        $content = match (true) {
            $html !== '' && (! $plain || $text === '') => $html,
            $text !== '' => '<pre class="plain">'.htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</pre>',
            default => null,
        };

        if ($content === null) {
            return null;
        }

        return '<!doctype html><html><head><meta charset="utf-8">'
            .'<meta http-equiv="Content-Security-Policy" content="'.self::POLICY.'">'
            .'<base target="_blank">'
            .'<style>'.self::STYLE.'</style>'
            .'</head><body>'.$content.'</body></html>';
    }
}
