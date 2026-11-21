<?php

declare(strict_types=1);

use App\Actions\Consumption\ConsumptionValues;
use App\Actions\Consumption\SaveConsumption;
use App\Enums\ConsumptionSource;
use App\Enums\ConsumptionUnit;
use App\Enums\UtilityType;
use App\Models\Document;
use App\Models\Family;
use App\Models\Utility;
use App\Models\UtilityConsumption;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;

/**
 * Values for a period, in September 2026 unless said otherwise.
 */
function saveConsumptionValues(float $quantity = 200.0, string $start = '2026-09-01', string $end = '2026-09-30'): ConsumptionValues
{
    return new ConsumptionValues(CarbonImmutable::parse($start), CarbonImmutable::parse($end), $quantity, 70.0, false);
}

/**
 * Runs the work inside a new family and gives it its electricity utility and two documents.
 *
 * @param  Closure(Utility, Document, Document): mixed  $work
 */
function saveConsumptionInFamily(Closure $work, UtilityType $type = UtilityType::Electricity): mixed
{
    $family = Family::factory()->create();

    return CurrentFamily::using($family->id, function () use ($family, $work, $type): mixed {
        $utility = Utility::factory()->create(['family_id' => $family->id, 'type' => $type]);

        return $work(
            $utility,
            Document::factory()->create(['family_id' => $family->id]),
            Document::factory()->create(['family_id' => $family->id]),
        );
    });
}

it('updates the row of the same document instead of adding another', function (): void {
    saveConsumptionInFamily(function (Utility $utility, Document $document): void {
        $first = resolve(SaveConsumption::class)->handle($utility, saveConsumptionValues(200.0), ConsumptionSource::Reading, $document);
        $second = resolve(SaveConsumption::class)->handle($utility, saveConsumptionValues(250.0, '2026-10-01', '2026-10-31'), ConsumptionSource::Reading, $document);

        expect($second->id)->toBe($first->id)
            ->and(UtilityConsumption::query()->count())->toBe(1)
            ->and((float) $second->quantity)->toBe(250.0)
            ->and($second->period_start->toDateString())->toBe('2026-10-01');
    });
});

it('takes the new document when another document has the same period of the same utility', function (): void {
    saveConsumptionInFamily(function (Utility $utility, Document $one, Document $two): void {
        $first = resolve(SaveConsumption::class)->handle($utility, saveConsumptionValues(), ConsumptionSource::Reading, $one);
        $second = resolve(SaveConsumption::class)->handle($utility, saveConsumptionValues(210.0), ConsumptionSource::Reading, $two);

        expect($second->id)->toBe($first->id)
            ->and($second->document_id)->toBe($two->id)
            ->and(UtilityConsumption::query()->count())->toBe(1);
    });
});

it('adds a row for another period', function (): void {
    saveConsumptionInFamily(function (Utility $utility, Document $one, Document $two): void {
        resolve(SaveConsumption::class)->handle($utility, saveConsumptionValues(), ConsumptionSource::Reading, $one);
        resolve(SaveConsumption::class)->handle($utility, saveConsumptionValues(180.0, '2026-10-01', '2026-10-31'), ConsumptionSource::Reading, $two);

        expect(UtilityConsumption::query()->count())->toBe(2);
    });
});

it('updates the row it is given and takes the source it is given', function (): void {
    saveConsumptionInFamily(function (Utility $utility): void {
        $row = UtilityConsumption::factory()->create(['family_id' => $utility->family_id, 'utility_id' => $utility->id, 'source' => ConsumptionSource::Reading]);

        $saved = resolve(SaveConsumption::class)->handle($utility, saveConsumptionValues(333.0, '2026-07-01', '2026-07-31'), ConsumptionSource::Manual, null, $row);

        expect($saved->id)->toBe($row->id)
            ->and($saved->source)->toBe(ConsumptionSource::Manual)
            ->and((float) $saved->quantity)->toBe(333.0)
            ->and($saved->period_end->toDateString())->toBe('2026-07-31')
            ->and(UtilityConsumption::query()->count())->toBe(1);
    });
});

it('leaves a manual row alone when a reading finds it', function (): void {
    saveConsumptionInFamily(function (Utility $utility, Document $document): void {
        $row = UtilityConsumption::factory()->create(['family_id' => $utility->family_id, 'utility_id' => $utility->id, 'document_id' => $document->id, 'source' => ConsumptionSource::Manual, 'quantity' => 200]);

        $byDocument = resolve(SaveConsumption::class)->handle($utility, saveConsumptionValues(999.0, '2026-09-01', '2026-09-30'), ConsumptionSource::Reading, $document);
        $byPeriod = resolve(SaveConsumption::class)->handle($utility, saveConsumptionValues(999.0), ConsumptionSource::Reading);

        expect($byDocument->id)->toBe($row->id)
            ->and($byPeriod->id)->toBe($row->id)
            ->and((float) $row->refresh()->quantity)->toBe(200.0)
            ->and($row->refresh()->source)->toBe(ConsumptionSource::Manual)
            ->and(UtilityConsumption::query()->count())->toBe(1);
    });
});

it('takes the unit from the type of the utility', function (): void {
    saveConsumptionInFamily(function (Utility $utility): void {
        $row = resolve(SaveConsumption::class)->handle($utility, saveConsumptionValues(12.0), ConsumptionSource::Reading);

        expect($row->unit)->toBe(ConsumptionUnit::M3);
    }, UtilityType::Water);
});

it('writes every field of the values', function (): void {
    saveConsumptionInFamily(function (Utility $utility): void {
        $values = new ConsumptionValues(CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30'), 300.0, 84.2, true, true, 60.5, 100.0, 120.0, 80.0);
        $row = resolve(SaveConsumption::class)->handle($utility, $values, ConsumptionSource::Reading)->refresh();

        expect((float) $row->amount)->toBe(84.2)
            ->and($row->is_estimated)->toBeTrue()
            ->and($row->has_adjustments)->toBeTrue()
            ->and((float) $row->energy_amount)->toBe(60.5)
            ->and((float) $row->quantity_f1)->toBe(100.0)
            ->and((float) $row->quantity_f2)->toBe(120.0)
            ->and((float) $row->quantity_f3)->toBe(80.0)
            ->and($row->family_id)->toBe($utility->family_id);
    });
});

it('refuses a utility that has no unit', function (): void {
    saveConsumptionInFamily(function (Utility $utility): void {
        resolve(SaveConsumption::class)->handle($utility, saveConsumptionValues(), ConsumptionSource::Reading);
    }, UtilityType::Internet);
})->throws(InvalidArgumentException::class);
