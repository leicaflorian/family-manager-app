<?php

declare(strict_types=1);

use App\Support\FileTitle;

test('the title is the file name without the extension', function (): void {
    expect(FileTitle::from('Busta paga novembre 2026.pdf'))->toBe('Busta paga novembre 2026');
});

test('dashes and underscores become spaces, and repeated spaces one', function (): void {
    expect(FileTitle::from('bolletta-edison_ottobre  2026.PDF'))->toBe('bolletta edison ottobre 2026');
});

test('only the last extension goes', function (): void {
    expect(FileTitle::from('scan.2026.10.jpeg'))->toBe('scan.2026.10');
});

test('a name with nothing but the extension keeps the whole name', function (): void {
    expect(FileTitle::from('.pdf'))->toBe('.pdf')
        ->and(FileTitle::from('___.png'))->toBe('___.png');
});
