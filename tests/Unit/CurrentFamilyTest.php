<?php

declare(strict_types=1);

use App\Support\CurrentFamily;

test('using() sets the family only while working and then puts back the previous one', function (): void {
    CurrentFamily::set(3);

    $inside = CurrentFamily::using(7, fn (): ?int => CurrentFamily::id());

    expect($inside)->toBe(7)
        ->and(CurrentFamily::id())->toBe(3);
});

test('using() puts back the previous family even if the work stops with an error', function (): void {
    expect(fn (): mixed => CurrentFamily::using(7, fn () => throw new RuntimeException('stopped')))
        ->toThrow(RuntimeException::class)
        ->and(CurrentFamily::id())->toBeNull();
});
