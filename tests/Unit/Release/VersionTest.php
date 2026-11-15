<?php

declare(strict_types=1);

use App\Enums\ReleaseBump;
use App\Services\Release\Version;

test('a patch bump raises the last number', function (): void {
    expect((string) Version::parse('2.0.0')->bump(ReleaseBump::Patch))->toBe('2.0.1');
});

test('a minor bump raises the middle number and resets the patch', function (): void {
    expect((string) Version::parse('2.0.4')->bump(ReleaseBump::Minor))->toBe('2.1.0');
});

test('a major bump raises the first number and resets the others', function (): void {
    expect((string) Version::parse('2.3.4')->bump(ReleaseBump::Major))->toBe('3.0.0');
});

test('the first version is 2.0.0', function (): void {
    expect(Version::FIRST)->toBe('2.0.0');
});

test('a value that is not X.Y.Z is refused', function (string $value): void {
    expect(fn (): Version => Version::parse($value))->toThrow(InvalidArgumentException::class);
})->with(['v2.0.0', '2.0', '2.0.0.1', '', '2.a.0']);
