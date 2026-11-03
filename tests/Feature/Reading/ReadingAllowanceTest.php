<?php

declare(strict_types=1);

use App\Models\AiReading;
use App\Models\Family;
use App\Services\Reading\ReadingAllowance;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config(['document_reader.monthly_limit' => 2]);
    $this->family = Family::factory()->create();
});

/**
 * A reading of the family made at a moment, stored as the app stores it (in the time zone of the app).
 *
 * @param  Family  $family  the family
 * @param  string  $at  the moment, in Rome
 */
function readingAt(Family $family, string $at): AiReading
{
    return AiReading::factory()->for($family)->create(['created_at' => CarbonImmutable::parse($at, 'Europe/Rome')->setTimezone(config('app.timezone'))]);
}

/**
 * The readings left to the family at a moment.
 *
 * @param  Family  $family  the family
 * @param  CarbonImmutable  $now  the moment
 */
function readingsLeft(Family $family, CarbonImmutable $now): int
{
    return CurrentFamily::using($family->id, fn (): int => resolve(ReadingAllowance::class)->left($now));
}

test('the limit less the readings of the month', function (): void {
    $now = CarbonImmutable::parse('2026-10-25 19:04', 'Europe/Rome');

    expect(readingsLeft($this->family, $now))->toBe(2);

    readingAt($this->family, '2026-10-03 10:00');
    readingAt($this->family, '2026-09-30 10:00');
    expect(readingsLeft($this->family, $now))->toBe(1);

    readingAt($this->family, '2026-10-20 10:00');
    readingAt($this->family, '2026-10-21 10:00');
    expect(readingsLeft($this->family, $now))->toBe(0);
});

test('only the readings of the current family count', function (): void {
    readingAt(Family::factory()->create(), '2026-10-03 10:00');
    readingAt(Family::factory()->create(), '2026-10-04 10:00');

    expect(readingsLeft($this->family, CarbonImmutable::parse('2026-10-25 19:04', 'Europe/Rome')))->toBe(2);
});

test('a reading at 00:30 of 1 November in Rome counts for November', function (): void {
    // 23:30 UTC of 31/10 is 00:30 of 01/11 in Rome
    readingAt($this->family, '2026-11-01 00:30');
    readingAt($this->family, '2026-10-31 23:00');

    expect(readingsLeft($this->family, CarbonImmutable::parse('2026-10-31 23:30', 'UTC')))->toBe(1)
        ->and(readingsLeft($this->family, CarbonImmutable::parse('2026-11-30 22:59', 'UTC')))->toBe(1)
        ->and(readingsLeft($this->family, CarbonImmutable::parse('2026-10-31 22:59', 'UTC')))->toBe(1);
});

test('with the limit used in October, 1 November at 00:30 in Rome reads again', function (): void {
    readingAt($this->family, '2026-10-31 23:00');
    readingAt($this->family, '2026-10-31 23:10');

    expect(readingsLeft($this->family, CarbonImmutable::parse('2026-10-31 23:20', 'Europe/Rome')))->toBe(0)
        ->and(readingsLeft($this->family, CarbonImmutable::parse('2026-11-01 00:30', 'Europe/Rome')))->toBe(2);
});
