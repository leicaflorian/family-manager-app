<?php

declare(strict_types=1);

use App\Support\Reading\ValidDate;

describe('dates', function (): void {
    test('validDate keeps a well-formed date', function (): void {
        expect(ValidDate::of('2026-03-15'))->toBe('2026-03-15');
    });

    test('validDate drops text and null', function (): void {
        expect(ValidDate::of('15/03/2026'))->toBeNull()
            ->and(ValidDate::of('domani'))->toBeNull()
            ->and(ValidDate::of(null))->toBeNull();
    });

    test('validDate drops a day that does not exist', function (): void {
        expect(ValidDate::of('2026-02-30'))->toBeNull()
            ->and(ValidDate::of('2026-13-01'))->toBeNull()
            ->and(ValidDate::of('2026-04-31'))->toBeNull()
            ->and(ValidDate::of('2026-00-10'))->toBeNull();
    });

    test('validDate keeps 29 February of a leap year only', function (): void {
        expect(ValidDate::of('2028-02-29'))->toBe('2028-02-29')
            ->and(ValidDate::of('2026-02-29'))->toBeNull();
    });
});
