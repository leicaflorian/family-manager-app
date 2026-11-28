<?php

declare(strict_types=1);

use App\Ai\Agents\ConsumptionReaderAgent;
use App\Data\Reading\ConsumptionReading;
use App\Data\Reading\SupplyConsumptionReading;
use App\Exceptions\Reading\ReadingFailed;
use App\Services\Reading\ReadingPrompt;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

test('the schema has the fields of a consumption reading, all required, only is_bill never null', function (): void {
    $properties = new ConsumptionReaderAgent('gemini-3.8-flash')->schema(new JsonSchemaTypeFactory);

    expect(array_keys($properties))->toBe([
        'is_bill', 'period_start', 'period_end', 'consumption_quantity', 'consumption_unit', 'total_amount',
        'consumption_estimated', 'consumption_adjusted', 'consumption_energy_amount', 'consumption_f1', 'consumption_f2', 'consumption_f3',
        'supplies',
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

/**
 * The answer of the model for the Enel bill of electricity and gas of the Bianchi.
 *
 * @param  list<array<string, mixed>>|null  $supplies  the supplies, null for the two of the bill
 * @return array<string, mixed>
 */
function consumptionReaderAgentEnel(?array $supplies = null): array
{
    return [
        'is_bill' => true, 'period_start' => null, 'period_end' => null, 'consumption_quantity' => null, 'consumption_unit' => null,
        'total_amount' => 165.65, 'consumption_estimated' => null, 'consumption_adjusted' => null, 'consumption_energy_amount' => null,
        'consumption_f1' => null, 'consumption_f2' => null, 'consumption_f3' => null,
        'supplies' => $supplies ?? [
            ['utility_type' => 'electricity', 'supply_point_code' => 'IT 001 E 87654321', 'amount' => 96.25, 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'consumption_quantity' => 201, 'consumption_unit' => 'kwh', 'consumption_estimated' => false, 'consumption_adjusted' => false, 'consumption_energy_amount' => 58.1, 'consumption_f1' => 80, 'consumption_f2' => 61, 'consumption_f3' => 60],
            ['utility_type' => 'gas', 'supply_point_code' => '00881234567890', 'amount' => 69.40, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'consumption_quantity' => 48, 'consumption_unit' => 'smc', 'consumption_estimated' => true, 'consumption_adjusted' => false, 'consumption_energy_amount' => 39.5, 'consumption_f1' => null, 'consumption_f2' => null, 'consumption_f3' => null],
        ],
    ];
}

test('the schema lists the supplies of a shared bill, each field required and nullable', function (): void {
    $supplies = new ConsumptionReaderAgent('gemini-3.8-flash')->schema(new JsonSchemaTypeFactory)['supplies']->toArray();
    $item = $supplies['items'];

    expect($supplies['type'])->toBe('array')
        ->and($item['type'])->toBe('object')
        ->and($item['additionalProperties'])->toBeFalse()
        ->and($item['required'])->toBe([
            'utility_type', 'supply_point_code', 'amount', 'period_start', 'period_end', 'consumption_quantity', 'consumption_unit',
            'consumption_estimated', 'consumption_adjusted', 'consumption_energy_amount', 'consumption_f1', 'consumption_f2', 'consumption_f3',
        ])
        ->and($item['properties']['utility_type']['enum'])->toBe(['electricity', 'gas', 'water', null])
        ->and($item['properties']['amount']['type'])->toBe(['number', 'null'])
        ->and($item['properties']['period_start']['format'])->toBe('date')
        ->and($item['properties']['consumption_unit']['enum'])->toBe(['kwh', 'smc', 'm3', null]);
});

test('the system text says when to fill the supplies', function (): void {
    expect(new ConsumptionReaderAgent('gemini-3.8-flash')->instructions())->toContain('supplies: Fill `supplies` only when the bill has two or more supplies');
});

test('the rule of the supplies shares its start with the full reading and keeps its own end', function (): void {
    expect(new ConsumptionReaderAgent('gemini-3.8-flash')->instructions())->toEndWith(
        "\n- supplies: Fill `supplies` only when the bill has two or more supplies, each with its own supply code (a dual-fuel bill); otherwise leave it empty. For each supply: utility_type, supply_point_code, amount (the total of that supply in euro), period_start, period_end and the consumption_* fields of that supply, with the rules above. total_amount stays the total to pay; period_start, period_end and the consumption_* fields of the document are those of the first supply.",
    )->toContain(ReadingPrompt::SUPPLIES_LIST);
});

test('the answer of a bill of electricity and gas keeps both supplies, and the flat fields are the first', function (): void {
    $reading = ConsumptionReaderAgent::parse(consumptionReaderAgentEnel());

    expect($reading->supplies)->toHaveCount(2)
        ->and($reading->supplies[0])->toEqual(new SupplyConsumptionReading('electricity', 'IT 001 E 87654321', new ConsumptionReading(
            isBill: true, periodStart: '2026-09-01', periodEnd: '2026-09-30', quantity: 201.0, unit: 'kwh', totalAmount: 96.25,
            estimated: false, adjusted: false, energyAmount: 58.1, f1: 80.0, f2: 61.0, f3: 60.0,
        )))
        ->and($reading->supplies[1]->utilityType)->toBe('gas')
        ->and($reading->supplies[1]->reading->quantity)->toBe(48.0)
        ->and($reading->supplies[1]->reading->totalAmount)->toBe(69.40)
        ->and($reading->quantity)->toBe(201.0)
        ->and($reading->unit)->toBe('kwh')
        ->and($reading->periodStart)->toBe('2026-09-01')
        ->and($reading->f1)->toBe(80.0)
        ->and($reading->totalAmount)->toBe(165.65);
});

test('a single supply left, after the empty ones and those that are not objects, is no list', function (): void {
    $enel = consumptionReaderAgentEnel()['supplies'];
    $reading = ConsumptionReaderAgent::parse(consumptionReaderAgentEnel([
        $enel[1],
        ['utility_type' => 'steam', 'supply_point_code' => null, 'amount' => null],
        'gas',
    ]));

    expect($reading->supplies)->toBe([])
        ->and($reading->quantity)->toBeNull()
        ->and($reading->totalAmount)->toBe(165.65);
});

test('a type or a unit out of the choices becomes null', function (): void {
    $enel = consumptionReaderAgentEnel()['supplies'];
    $reading = ConsumptionReaderAgent::parse(consumptionReaderAgentEnel([
        [...$enel[0], 'utility_type' => 'steam'],
        [...$enel[1], 'consumption_unit' => 'litri'],
    ]));

    expect($reading->supplies[0]->utilityType)->toBeNull()
        ->and($reading->supplies[1]->reading->unit)->toBeNull();
});
