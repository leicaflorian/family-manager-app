<?php

declare(strict_types=1);

use App\Ai\Agents\ConsumptionReaderAgent;
use App\Data\Reading\ConsumptionReading;
use App\Exceptions\Reading\ReadingFailed;
use App\Services\Reading\ReadingPrompt;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

test('the schema has the fields of a consumption reading, all required, only is_bill never null', function (): void {
    $properties = new ConsumptionReaderAgent('gemini-3.8-flash')->schema(new JsonSchemaTypeFactory);

    expect(array_keys($properties))->toBe([
        'is_bill', 'period_start', 'period_end', 'consumption_quantity', 'consumption_unit', 'total_amount',
        'consumption_estimated', 'consumption_adjusted', 'consumption_energy_amount', 'consumption_f1', 'consumption_f2', 'consumption_f3',
    ])
        ->and($properties['is_bill']->toArray()['type'])->toBe('boolean')
        ->and($properties['consumption_quantity']->toArray()['type'])->toBe(['number', 'null'])
        ->and($properties['consumption_unit']->toArray()['enum'])->toBe(['kwh', 'smc', 'm3', null])
        ->and($properties['period_start']->toArray()['format'])->toBe('date');
});

test('the system text keeps the rules of the consumption of the full reading', function (): void {
    expect(new ConsumptionReaderAgent('gemini-3.8-flash')->instructions())
        ->toContain(ReadingPrompt::CONSUMPTION_RULE)
        ->toContain(ReadingPrompt::PERIOD_RULE)
        ->toContain('utility bill of electricity, gas or water');
});

test('a valid answer becomes a consumption reading', function (): void {
    $reading = ConsumptionReaderAgent::parse('{"is_bill": true, "period_start": "2026-08-01", "period_end": "2026-09-30", "consumption_quantity": 312, "consumption_unit": "kwh", "total_amount": 96.4, "consumption_estimated": false, "consumption_adjusted": null, "consumption_energy_amount": 51.2, "consumption_f1": 120.5, "consumption_f2": null, "consumption_f3": null}');

    expect($reading)->toEqual(new ConsumptionReading(
        isBill: true,
        periodStart: '2026-08-01',
        periodEnd: '2026-09-30',
        quantity: 312.0,
        unit: 'kwh',
        totalAmount: 96.4,
        estimated: false,
        energyAmount: 51.2,
        f1: 120.5,
    ));
});

test('a structured answer of something that is not a bill keeps only that', function (): void {
    $reading = ConsumptionReaderAgent::parse(['is_bill' => false, 'consumption_quantity' => 'tanto', 'period_start' => 7]);

    expect($reading->isBill)->toBeFalse()
        ->and($reading->quantity)->toBeNull()
        ->and($reading->periodStart)->toBeNull();
});

test('a broken answer is a paid failure', function (array|string|null $answer): void {
    try {
        ConsumptionReaderAgent::parse($answer);
        $failure = null;
    } catch (ReadingFailed $e) {
        $failure = $e;
    }

    expect($failure)->toBeInstanceOf(ReadingFailed::class)
        ->and($failure?->paid)->toBeTrue();
})->with([
    'not json' => ['{"is_bill": tru'],
    'a list' => ['[1, 2]'],
    'no is_bill' => [['consumption_quantity' => 3.0]],
    'empty' => [null],
]);
