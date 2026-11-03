<?php

declare(strict_types=1);

use App\Support\Reading\ReadingCost;
use Carbon\CarbonImmutable;

/**
 * Estimates the cost of a reading of one million tokens in and out.
 */
function millionTokensCost(string $model, string $day, int $input = 1_000_000, int $output = 1_000_000): ?float
{
    return ReadingCost::estimate($model, $input, $output, CarbonImmutable::parse($day, 'Europe/Rome'));
}

test('a model with a date suffix takes the price of the base model', function (): void {
    // haiku 4.5: 1 dollar input + 5 dollars output per million tokens
    expect(millionTokensCost('claude-haiku-4-5-20251001', '2026-10-01'))->toBe(6.0);
});

test('gemini-3.5-flash-lite does not take the price of gemini-3.5-flash', function (): void {
    // lite: 0.3 + 2.5; flash would be 1.5 + 9
    expect(millionTokensCost('gemini-3.5-flash-lite', '2026-10-01'))->toEqualWithDelta(2.8, 1e-9);
});

test('gemini-3.5-flash has its own price', function (): void {
    expect(millionTokensCost('gemini-3.5-flash', '2026-10-01'))->toEqualWithDelta(10.5, 1e-9);
});

test('an unknown model gives null', function (): void {
    expect(millionTokensCost('modello-sconosciuto', '2026-10-01'))->toBeNull();
});

test('a model with a price that starts on a day: the day before and the day itself differ', function (): void {
    // gemini-3.8-flash: 0.75 + 3.75 until 2026-12-31, 1.5 + 7.5 from 2027-01-01
    expect(millionTokensCost('gemini-3.8-flash', '2026-12-31'))->toEqualWithDelta(4.5, 1e-9)
        ->and(millionTokensCost('gemini-3.8-flash', '2027-01-01'))->toEqualWithDelta(9.0, 1e-9);
});

test('gemini 3.8 flash costs double from 2027', function (): void {
    expect(millionTokensCost('gemini-3.8-flash', '2026-12-31', 6_000, 500))->toEqualWithDelta(0.00638, 0.00001)
        ->and(millionTokensCost('gemini-3.8-flash', '2027-01-01', 6_000, 500))->toEqualWithDelta(0.01275, 0.00001);
});
