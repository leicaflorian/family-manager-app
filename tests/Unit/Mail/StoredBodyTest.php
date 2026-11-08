<?php

declare(strict_types=1);

use App\Support\Mail\StoredBody;

beforeEach(function (): void {
    config(['app.locale' => 'it']);
});

test('the limits are 200,000 characters of text and 1 MiB of html', function (): void {
    expect(StoredBody::MAX_TEXT_CHARS)->toBe(200_000);
    expect(StoredBody::MAX_HTML_BYTES)->toBe(1024 * 1024);
});

test('keeps a short text and html as they are', function (): void {
    expect(StoredBody::of('hello', '<p>hello</p>'))->toBe(['plainText' => 'hello', 'html' => '<p>hello</p>']);
});

test('keeps a text with no html', function (): void {
    expect(StoredBody::of('hello', null))->toBe(['plainText' => 'hello', 'html' => null]);
});

test('a text of exactly MAX_TEXT_CHARS is not cut', function (): void {
    $text = str_repeat('a', StoredBody::MAX_TEXT_CHARS);
    expect(StoredBody::of($text, ''))->toBe(['plainText' => $text, 'html' => null]);
});

test('a text over MAX_TEXT_CHARS is cut and marked', function (): void {
    expect(StoredBody::of(str_repeat('a', StoredBody::MAX_TEXT_CHARS + 1), ''))
        ->toBe(['plainText' => str_repeat('a', StoredBody::MAX_TEXT_CHARS)."\n[testo troncato]", 'html' => null]);
});

test('the end of a cut text is trimmed before the marker', function (): void {
    $result = StoredBody::of(str_repeat('a', StoredBody::MAX_TEXT_CHARS - 3).'   '.str_repeat('b', 10), '');
    expect($result['plainText'] ?? null)->toBe(str_repeat('a', StoredBody::MAX_TEXT_CHARS - 3)."\n[testo troncato]");
});

test('html of exactly MAX_HTML_BYTES is kept', function (): void {
    $html = str_repeat('h', StoredBody::MAX_HTML_BYTES);
    expect(StoredBody::of('x', $html))->toBe(['plainText' => 'x', 'html' => $html]);
});

test('html over MAX_HTML_BYTES is dropped, with its size', function (): void {
    expect(StoredBody::of('x', str_repeat('h', StoredBody::MAX_HTML_BYTES + 1)))
        ->toBe(['plainText' => 'x', 'html' => null, 'htmlBytes' => StoredBody::MAX_HTML_BYTES + 1]);
});

test('the html size is counted in bytes, not characters', function (): void {
    // 2 bytes per character: 524289 characters are 1048578 bytes
    $html = str_repeat('é', intdiv(StoredBody::MAX_HTML_BYTES, 2) + 1);
    expect(StoredBody::of('', $html))->toBe(['plainText' => null, 'html' => null, 'htmlBytes' => StoredBody::MAX_HTML_BYTES + 2]);
});

test('an empty body is null', function (): void {
    expect(StoredBody::of('', ''))->toBeNull();
    expect(StoredBody::of(null, null))->toBeNull();
});
