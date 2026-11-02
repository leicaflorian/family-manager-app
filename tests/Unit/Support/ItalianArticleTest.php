<?php

declare(strict_types=1);

use App\Support\ItalianArticle;

test('in Italian «il» before a day that starts with a vowel sound becomes «l\'»: 1, 01, 8, 08 and 11', function (string $before, string $after): void {
    app()->setLocale('it');

    expect(ItalianArticle::elide($before))->toBe($after);
})->with([
    ['scade il 1/12/2026', "scade l'1/12/2026"],
    ['scade il 01/12/2026', "scade l'01/12/2026"],
    ['scade il 8/12/2026', "scade l'8/12/2026"],
    ['scade il 08/12/2026', "scade l'08/12/2026"],
    ['scade il 11/12/2026', "scade l'11/12/2026"],
    ['Il 11/12/2026 scade', "L'11/12/2026 scade"],
    ['partito il 07/10/2026 e il 11/11/2026', "partito il 07/10/2026 e l'11/11/2026"],
]);

test('the other days, and the dates that only contain those digits, are left alone', function (string $text): void {
    app()->setLocale('it');

    expect(ItalianArticle::elide($text))->toBe($text);
})->with([
    'il 18/12/2026',
    'il 21/11/2026',
    'il 10/11/2026',
    'il 12/01/2026',
    'il 111/2026',
    'il promemoria',
]);

test('in English nothing changes', function (): void {
    app()->setLocale('en');

    expect(ItalianArticle::elide('scade il 11/12/2026'))->toBe('scade il 11/12/2026');
});
