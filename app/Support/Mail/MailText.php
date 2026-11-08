<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Dom\Element;
use Dom\HTMLDocument;
use Throwable;
use UnexpectedValueException;

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

    /** The namespace of HTML tags: any other is svg or math. */
    private const string HTML_NAMESPACE = 'http://www.w3.org/1999/xhtml';

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
     * The content read as a browser reads it, with every `meta` and `base` of the mail taken out, and every `svg`
     * and `math` with what they hold, and the tags of the page put first in its head. A `meta` of the mail could
     * move the frame to a page of strangers (`refresh`, which the sandbox allows) or loosen the policy: with a real
     * parser no spelling, entity or broken tag slips past, as it would past a pattern. Inside `svg` and `math` a
     * text written out again can be read by the browser as tags, so they go too (a bill does not need them, and
     * their images are blocked anyway). The page written out is read once more, and used only when it holds
     * nothing but its own tags.
     *
     * @param  string  $content  the HTML of the mail, or its plain text already escaped
     * @return string|null the page, null when the content cannot be read or is not the same once written out
     */
    private static function page(string $content): ?string
    {
        $document = self::parse($content);

        if (! $document instanceof HTMLDocument || ! $document->documentElement instanceof Element) {
            return null;
        }

        foreach (iterator_to_array($document->getElementsByTagName('*'), false) as $element) {
            if ($element->namespaceURI !== self::HTML_NAMESPACE || in_array($element->localName, ['meta', 'base'], true)) {
                $element->remove();
            }
        }

        $document->head?->prepend(
            self::element($document, 'meta', ['charset' => 'utf-8']),
            self::element($document, 'meta', ['http-equiv' => 'Content-Security-Policy', 'content' => self::POLICY]),
            self::element($document, 'base', ['target' => '_blank']),
            self::element($document, 'style', [], self::STYLE),
        );

        $page = '<!doctype html>'.$document->saveHtml($document->documentElement);

        if (! self::onlyOwnTags($page)) {
            report(new UnexpectedValueException('The text of a received mail reads differently once written out.'));

            return null;
        }

        return $page;
    }

    /**
     * The HTML read as a browser reads it.
     *
     * @param  string  $html  the HTML
     * @return HTMLDocument|null the page, null when it cannot be read (the error is reported)
     */
    private static function parse(string $html): ?HTMLDocument
    {
        try {
            return HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Whether the page, read again as the browser will read it, has only the `meta` and `base` tags of the page,
     * in their place, and no `svg` or `math`.
     *
     * @param  string  $page  the page written out
     */
    private static function onlyOwnTags(string $page): bool
    {
        $document = self::parse($page);

        if (! $document instanceof HTMLDocument) {
            return false;
        }

        $tags = array_map(
            static fn (Element $element): string => implode('|', [
                $element->namespaceURI === self::HTML_NAMESPACE ? $element->localName : 'foreign',
                $element->getAttribute('charset') ?? '',
                $element->getAttribute('http-equiv') ?? '',
                $element->getAttribute('content') ?? '',
                $element->getAttribute('href') ?? '',
                $element->getAttribute('target') ?? '',
            ]),
            array_values(array_filter(
                iterator_to_array($document->getElementsByTagName('*'), false),
                static fn (Element $element): bool => $element->namespaceURI !== self::HTML_NAMESPACE || in_array($element->localName, ['meta', 'base'], true),
            )),
        );

        return $tags === [
            'meta|utf-8||||',
            'meta||Content-Security-Policy|'.self::POLICY.'||',
            'base|||||_blank',
        ];
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
