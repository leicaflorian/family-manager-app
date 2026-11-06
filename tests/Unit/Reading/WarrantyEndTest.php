<?php

declare(strict_types=1);

use App\Services\Reading\RecordDrafts;
use Carbon\CarbonImmutable;

test('the purchase date plus 24 months', function (): void {
    $end = RecordDrafts::warrantyEnd(CarbonImmutable::parse('2026-10-03'), null);

    expect($end?->toDateString())->toBe('2028-10-03');
});

test('29 February ends on 28 February', function (): void {
    $end = RecordDrafts::warrantyEnd(CarbonImmutable::parse('2024-02-29'), null);

    expect($end?->toDateString())->toBe('2026-02-28');
});

test('the printed date wins', function (): void {
    $end = RecordDrafts::warrantyEnd(CarbonImmutable::parse('2026-10-03'), CarbonImmutable::parse('2029-10-03'));

    expect($end?->toDateString())->toBe('2029-10-03');
});

test('no purchase date and nothing printed: none', function (): void {
    expect(RecordDrafts::warrantyEnd(null, null))->toBeNull();
});
