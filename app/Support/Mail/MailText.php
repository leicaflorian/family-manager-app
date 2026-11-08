<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Dom\Element;
use Dom\HTMLDocument;
use Throwable;

/**
 * The page the text of a received mail is shown with, inside a frame (`previewDocument` of Directus). The HTML goes
 * in as it came except its `meta` tags: scripts and requests are stopped by the browser, with the sandbox of the frame
 * and the policy of the page.
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
        $escaped = $text === '' ? null : '<pre class="plain">'.htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</pre>';

        if ($html !== '' && (! $plain || $escaped === null)) {
            return self::page($html) ?? ($escaped === null ? null : self::page($escaped));
        }

        return $escaped === null ? null : self::page($escaped);
    }

    /**
     * The content read as a browser reads it, with every `meta` of the mail taken out and the ones of the page put
     * first in its head. A `meta` of the mail could move the frame to a page of strangers (`refresh`, which the
     * sandbox allows) or loosen the policy: with a real parser no spelling, entity or broken tag slips past, as
     * it would past a pattern.
     *
     * @param  string  $content  the HTML of the mail, or its plain text already escaped
     * @return string|null the page, null when the content cannot be read
     */
    private static function page(string $content): ?string
    {
        try {
            $document = HTMLDocument::createFromString($content, LIBXML_NOERROR, 'UTF-8');
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        foreach (iterator_to_array($document->getElementsByTagName('meta'), false) as $meta) {
            $meta->remove();
        }

        $document->head?->prepend(
            self::element($document, 'meta', ['charset' => 'utf-8']),
            self::element($document, 'meta', ['http-equiv' => 'Content-Security-Policy', 'content' => self::POLICY]),
            self::element($document, 'base', ['target' => '_blank']),
            self::element($document, 'style', [], self::STYLE),
        );

        return $document->documentElement instanceof Element ? '<!doctype html>'.$document->saveHtml($document->documentElement) : null;
    }

    /**
     * An element of the page, with its attributes and its text.
     *
     * @param  HTMLDocument  $document  the page
     * @param  string  $name  the tag
     * @param  array<string, string>  $attributes  the attributes, in order
     * @param  string  $text  the text inside
     */
    private static function element(HTMLDocument $document, string $name, array $attributes, string $text = ''): Element
    {
        $element = $document->createElement($name);

        foreach ($attributes as $attribute => $value) {
            $element->setAttribute($attribute, $value);
        }

        $element->textContent = $text;

        return $element;
    }
}
