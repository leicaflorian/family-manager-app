<?php

declare(strict_types=1);

use App\Support\Reading\Codes;

describe('code', function (): void {
    test('keeps uppercase letters and digits only', function (): void {
        expect(Codes::normalize('it 001 e-1234.5678'))->toBe('IT001E12345678');
    });

    test('empty for null', function (): void {
        expect(Codes::normalize(null))->toBe('')
            ->and(Codes::normalize(''))->toBe('');
    });
});
