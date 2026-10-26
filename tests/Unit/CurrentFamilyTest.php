<?php

declare(strict_types=1);

use App\Support\CurrentFamily;

test('per() imposta la famiglia solo mentre lavora e poi rimette quella di prima', function (): void {
    CurrentFamily::set(3);

    $dentro = CurrentFamily::per(7, fn (): ?int => CurrentFamily::id());

    expect($dentro)->toBe(7)
        ->and(CurrentFamily::id())->toBe(3);
});

test('per() rimette la famiglia di prima anche se il lavoro si ferma con un errore', function (): void {
    expect(fn (): mixed => CurrentFamily::per(7, fn () => throw new RuntimeException('fermo')))
        ->toThrow(RuntimeException::class)
        ->and(CurrentFamily::id())->toBeNull();
});
