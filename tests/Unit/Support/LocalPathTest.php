<?php

declare(strict_types=1);

use App\Support\LocalPath;

test('it keeps a path of the app, with its query', function (): void {
    expect(LocalPath::of('/cards/vehicle/3?tab=1'))->toBe('/cards/vehicle/3?tab=1')
        ->and(LocalPath::of('/'))->toBe('/');
});

test('it refuses anything that could lead to another site or is not a path', function (mixed $value): void {
    expect(LocalPath::of($value))->toBeNull();
})->with([
    'another site' => 'https://example.com/agenda',
    'no scheme' => '//example.com',
    'a backslash' => '/\\example.com',
    'not a path' => 'agenda',
    'empty' => '',
    'null' => null,
    'an array' => [['/agenda']],
    'a line break' => "/agenda\r\nLocation: https://example.com",
]);
