<?php

declare(strict_types=1);

use App\Enums\FileSource;
use App\Support\ScanFileName;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->at = CarbonImmutable::parse('2026-10-25 19:04', 'Europe/Rome');
});

test('a phone upload is called after the day and the time of Rome', function (): void {
    expect(ScanFileName::for(FileSource::Phone, $this->at, 'pdf', collect()))->toBe('Telefono 2026-10-25 19.04.pdf');
    expect(ScanFileName::for(FileSource::Scanner, CarbonImmutable::parse('2026-10-14 18:32', 'Europe/Rome'), 'pdf', collect()))->toBe('Scanner 2026-10-14 18.32.pdf');
});

test('the time is the one of Rome, not UTC', function (): void {
    $utc = CarbonImmutable::parse('2026-10-31 22:30', 'UTC');

    expect(ScanFileName::for(FileSource::Phone, $utc, 'pdf', collect()))->toBe('Telefono 2026-10-31 23.30.pdf');
});

test('the same minute takes a number', function (): void {
    $taken = collect(['Telefono 2026-10-25 19.04.pdf']);
    expect(ScanFileName::for(FileSource::Phone, $this->at, 'pdf', $taken))->toBe('Telefono 2026-10-25 19.04 (2).pdf');

    $taken->push('Telefono 2026-10-25 19.04 (2).pdf');
    expect(ScanFileName::for(FileSource::Phone, $this->at, 'pdf', $taken))->toBe('Telefono 2026-10-25 19.04 (3).pdf');
});

test('a name written for the scanner is cleaned of a second pdf and of the signs a name cannot hold', function (): void {
    expect(ScanFileName::for(FileSource::Scanner, $this->at, 'pdf', collect(), ' polizza  casa.PDF'))->toBe('polizza casa.pdf');
    expect(ScanFileName::for(FileSource::Scanner, $this->at, 'pdf', collect(), 'bollette 10/2026: luce'))->toBe('bollette 10 2026 luce.pdf');
    expect(ScanFileName::for(FileSource::Scanner, $this->at, 'pdf', collect(), ' / '))->toBe('Scanner 2026-10-25 19.04.pdf');
    expect(ScanFileName::for(FileSource::Scanner, $this->at, 'pdf', collect(['polizza.pdf']), 'polizza'))->toBe('polizza (2).pdf');
});

test('a name written is cleaned of control characters', function (): void {
    expect(ScanFileName::for(FileSource::Scanner, $this->at, 'pdf', collect(), "po\nlizza\0 casa\t2026\x7f"))->toBe('po lizza casa 2026.pdf');
    expect(ScanFileName::for(FileSource::Scanner, $this->at, 'pdf', collect(), "\n\0"))->toBe('Scanner 2026-10-25 19.04.pdf');
});
