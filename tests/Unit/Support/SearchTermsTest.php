<?php

declare(strict_types=1);

use App\Support\SearchTerms;
use Carbon\CarbonImmutable;

test('the query is trimmed and split into words', function (): void {
    $terms = SearchTerms::fromQuery('  Fiat   Panda ');

    expect($terms->query)->toBe('Fiat Panda')
        ->and($terms->words)->toBe(['Fiat', 'Panda']);
});

test('a query is searchable from two characters', function (string $query, bool $expected): void {
    expect(SearchTerms::fromQuery($query)->isSearchable())->toBe($expected);
})->with([['P', false], ['   ', false], ['Pa', true]]);

test('an amount is read with the comma or the point', function (string $query, ?string $expected): void {
    expect(SearchTerms::fromQuery($query)->amount)->toBe($expected);
})->with([
    ['84,20', '84.20'],
    ['84.20', '84.20'],
    ['1.488,79', '1488.79'],
    ['84', '84.00'],
    ['84,2', '84.20'],
    ['Panda', null],
    ['2026', '2026.00'],
]);

test('a date is read as day and month, with the year or without', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-09'));

    expect(SearchTerms::fromQuery('15/10/2026')->date?->toDateString())->toBe('2026-10-15')
        ->and(SearchTerms::fromQuery('15/10')->date?->toDateString())->toBe('2026-10-15')
        ->and(SearchTerms::fromQuery('31/02/2026')->date)->toBeNull()
        ->and(SearchTerms::fromQuery('2026-10-15')->date)->toBeNull();
});

test('like escapes the wildcards', function (): void {
    expect(SearchTerms::like('50%_a\\b'))->toBe('%50\%\_a\\\\b%');
});

test('a long query is cut to 100 characters', function (): void {
    expect(SearchTerms::fromQuery(str_repeat('a', 150))->query)->toHaveLength(100);
});
