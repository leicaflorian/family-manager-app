<?php

declare(strict_types=1);

use App\Support\Mail\MailText;
use Dom\Element;
use Dom\HTMLDocument;

/**
 * The `meta` elements of the page for the frame, as a browser reads them.
 *
 * @return list<string> the `http-equiv` of each, empty when it has none
 */
function framePageMetas(string $page): array
{
    $document = HTMLDocument::createFromString($page, LIBXML_NOERROR, 'UTF-8');

    return array_map(
        static fn (Element $meta): string => strtolower($meta->getAttribute('http-equiv') ?? ''),
        iterator_to_array($document->getElementsByTagName('meta'), false),
    );
}

test('the page keeps only its own meta tags, whatever the mail writes', function (string $html): void {
    $page = (string) MailText::document(['plainText' => null, 'html' => $html.'<p>Ciao</p>'], false);

    expect(framePageMetas($page))->toBe(['', 'content-security-policy'])
        ->and($page)->toContain('<p>Ciao</p>');
})->with([
    'refresh' => '<meta http-equiv="refresh" content="0;url=https://phishing.example">',
    'upper case, no quotes' => '<META HTTP-EQUIV=Refresh CONTENT="1; URL=https://x.example">',
    'put back together after one pass' => '<me<meta http-equiv=refresh>ta http-equiv=refresh content="0;url=//e.x">',
    'a > inside a quoted attribute' => '<meta content="0;url=a>b" http-equiv="refresh">',
    'an encoded value' => '<meta http-equiv="&#114;efresh" content="0;url=//e.x">',
    'a policy of its own' => '<meta http-equiv="Content-Security-Policy" content="default-src *">',
    'inside svg' => '<svg><meta http-equiv="refresh" content="0;url=//e.x"></svg>',
    'a whole page' => '<!doctype html><html><head><meta charset="iso-8859-1"><meta http-equiv="refresh" content="0;url=//e.x"></head><body>',
]);

test('the policy of the page comes before anything of the mail', function (): void {
    $page = (string) MailText::document(['html' => '<html><head><style>p { color: red; }</style><base href="https://e.x/"></head><body><p>Ciao</p></body></html>'], false);

    expect(strpos($page, 'Content-Security-Policy'))->toBeLessThan((int) strpos($page, 'color: red'))
        ->and(strpos($page, '<base target="_blank">'))->toBeLessThan((int) strpos($page, 'https://e.x/'))
        ->and($page)->toStartWith('<!doctype html>')
        ->toContain('<style>p { color: red; }</style>');
});

test('the mail keeps its images, links and the look of its body', function (): void {
    $page = (string) MailText::document(['html' => '<body bgcolor="#eeeeee" style="margin:0"><p>Ciao <img src="https://tracker.example/x.png"> <a href="https://e.x/?a=1&amp;b=2">link</a></p></body>'], false);

    expect($page)->toContain('<p>Ciao <img src="https://tracker.example/x.png"> <a href="https://e.x/?a=1&amp;b=2">link</a></p>')
        ->toContain('bgcolor="#eeeeee"')
        ->toContain('style="margin:0"');
});

test('a broken html does not stop the page', function (): void {
    $page = (string) MailText::document(['html' => "<table><tr><td>uno<div>due</p></b>\xff<meta http-equiv=refresh"], false);

    expect(framePageMetas($page))->toBe(['', 'content-security-policy'])
        ->and($page)->toContain('uno')->toContain('due');
});

test('the plain text is still written as text', function (): void {
    $page = (string) MailText::document(['plainText' => 'Testo <meta http-equiv=refresh>', 'html' => '<p>Originale</p>'], true);

    expect($page)->toContain('<pre class="plain">Testo &lt;meta http-equiv=refresh&gt;</pre>')
        ->not->toContain('Originale')
        ->and(framePageMetas($page))->toBe(['', 'content-security-policy']);
});

test('no text gives no page', function (): void {
    expect(MailText::document(['plainText' => '', 'html' => ''], false))->toBeNull()
        ->and(MailText::document([], true))->toBeNull();
});

test('a mail as long as the stored limit is ready in a moment', function (): void {
    $html = str_repeat('<div><p class="x">Ciao <b>mondo</b> &amp; altro</p><meta http-equiv=refresh></div>', 12_000);
    $start = microtime(true);

    $page = (string) MailText::document(['html' => $html], false);

    expect(microtime(true) - $start)->toBeLessThan(2.0)
        ->and(framePageMetas($page))->toBe(['', 'content-security-policy']);
});
