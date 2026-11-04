<?php

declare(strict_types=1);

use App\Models\AiReading;
use App\Models\Family;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 19:04', 'Europe/Rome'));
    $this->bianchi = Family::factory()->create(['name' => 'Bianchi']);
    $this->rossi = Family::factory()->create(['name' => 'Rossi']);
});

/**
 * A reading of a family made at a moment of Rome, stored in the time zone of the app.
 *
 * @param  Family  $family  the family
 * @param  string  $at  the moment, in Rome
 * @param  array<string, mixed>  $attributes  columns to override
 */
function reportReading(Family $family, string $at, array $attributes = []): AiReading
{
    return AiReading::factory()->for($family)->create([
        'input_tokens' => 1000,
        'output_tokens' => 100,
        'cost_usd' => '0.002500',
        'created_at' => CarbonImmutable::parse($at, 'Europe/Rome')->setTimezone(config('app.timezone')),
        ...$attributes,
    ]);
}

test('counts the readings of the month of each family and their cost', function (): void {
    reportReading($this->bianchi, '2026-10-03 10:00');
    reportReading($this->bianchi, '2026-10-12 10:00');
    reportReading($this->bianchi, '2026-10-31 23:30');
    reportReading($this->bianchi, '2026-11-01 00:30');
    reportReading($this->rossi, '2026-10-05 10:00');

    $this->artisan('ai-readings:report', ['--month' => '2026-10'])
        ->expectsTable(
            ['Family', 'Readings', 'Input tokens', 'Output tokens', 'Cost'],
            [
                ['Bianchi', '3', '3000', '300', '0,0075 $'],
                ['Rossi', '1', '1000', '100', '0,0025 $'],
                ['Total', '4', '4000', '400', '0,0100 $'],
            ],
        )
        ->assertSuccessful();
});

test('counts the readings without a price apart', function (): void {
    reportReading($this->bianchi, '2026-10-03 10:00');
    reportReading($this->bianchi, '2026-10-04 10:00', ['cost_usd' => null]);
    reportReading($this->bianchi, '2026-10-05 10:00', ['cost_usd' => null]);
    reportReading($this->bianchi, '2026-10-06 10:00', ['cost_usd' => null]);

    $this->artisan('ai-readings:report', ['--month' => '2026-10'])
        ->expectsTable(
            ['Family', 'Readings', 'Input tokens', 'Output tokens', 'Cost'],
            [
                ['Bianchi', '4', '4000', '400', '0,0025 $ (3 without a price)'],
                ['Total', '4', '4000', '400', '0,0025 $ (3 without a price)'],
            ],
        )
        ->assertSuccessful();
});

test('without a month it reports the current month of Rome', function (): void {
    // 23:30 UTC of 31/10 is already November in Rome
    Date::setTestNow(CarbonImmutable::parse('2026-10-31 23:30', 'UTC'));
    reportReading($this->bianchi, '2026-10-20 10:00');
    reportReading($this->rossi, '2026-11-01 00:10');

    $this->artisan('ai-readings:report')
        ->expectsTable(
            ['Family', 'Readings', 'Input tokens', 'Output tokens', 'Cost'],
            [
                ['Rossi', '1', '1000', '100', '0,0025 $'],
                ['Total', '1', '1000', '100', '0,0025 $'],
            ],
        )
        ->assertSuccessful();
});

test('says so when the month has no readings', function (): void {
    reportReading($this->bianchi, '2026-11-02 10:00');

    $this->artisan('ai-readings:report', ['--month' => '2026-10'])
        ->expectsOutput('No readings in October 2026.')
        ->assertSuccessful();
});

test('refuses a month that is not valid', function (string $month): void {
    $this->artisan('ai-readings:report', ['--month' => $month])
        ->expectsOutput('--month must be a month like 2026-10.')
        ->assertFailed();
})->with(['2026-13', '2026-1', 'october', '2026-10-01', '2026-00']);
