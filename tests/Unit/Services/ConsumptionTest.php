<?php

declare(strict_types=1);

use App\Data\Consumption\ConsumptionMonthData;
use App\Enums\ConsumptionSource;
use App\Enums\ConsumptionUnit;
use App\Enums\UtilityType;
use App\Models\Document;
use App\Models\UtilityConsumption;
use App\Services\Consumption;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * A bill made in memory.
 *
 * @param  bool|null  $paid  null for a row without a document, otherwise whether its document has an expense
 */
function consumptionTestRow(string $start, string $end, float $quantity, ?float $amount, bool $estimated = false, bool $adjusted = false, ?bool $paid = null, int $id = 1): UtilityConsumption
{
    $row = new UtilityConsumption([
        'period_start' => $start,
        'period_end' => $end,
        'quantity' => $quantity,
        'unit' => ConsumptionUnit::Kwh,
        'amount' => $amount,
        'is_estimated' => $estimated,
        'has_adjustments' => $adjusted,
        'source' => $paid === null ? ConsumptionSource::Manual : ConsumptionSource::Reading,
    ]);
    $row->id = $id;

    if ($paid !== null) {
        $document = new Document;
        $document->id = $id + 1000;
        $document->setAttribute('expenses_exists', $paid);
        $row->document_id = $document->id;
        $row->setRelation('document', $document);
    } else {
        $row->setRelation('document', null);
    }

    return $row;
}

/**
 * One bill a month from a first month, each a whole month.
 *
 * @param  list<array{float, float}>  $months  quantity and amount of each month
 * @param  list<int>  $estimated  the indexes of the estimated bills
 * @return Collection<int, UtilityConsumption>
 */
function consumptionTestMonthly(string $first, array $months, array $estimated = [], ?int $unpaid = null): Collection
{
    $start = CarbonImmutable::parse($first);

    return collect($months)->map(function (array $values, int $index) use ($start, $estimated, $unpaid): UtilityConsumption {
        $month = $start->addMonthsNoOverflow($index);

        return consumptionTestRow($month->toDateString(), $month->endOfMonth()->toDateString(), $values[0], $values[1], in_array($index, $estimated, true), paid: $index === $unpaid ? false : null, id: $index + 1);
    })->values();
}

/**
 * The 24 months of electricity of the Bianchi in the seeder, October 2024 – September 2026.
 *
 * @return list<array{float, float}>
 */
function consumptionTestBianchi(): array
{
    return [
        [198, 55.84], [220, 62.70], [245, 71.05], [248, 71.42], [230, 65.78], [214, 60.78], [190, 53.20], [176, 49.10], [184, 52.07], [228, 66.35], [172, 50.74], [188, 56.02],
        [214, 65.27], [236, 72.75], [262, 82.71], [290, 86.10], [268, 81.74], [240, 72.00], [205, 60.68], [188, 57.90], [196, 61.15], [242, 77.92], [165, 55.28], [205, 79.60],
    ];
}

/**
 * The month `aaaa-mm` among the months.
 *
 * @param  list<ConsumptionMonthData>  $months
 */
function consumptionTestMonth(array $months, string $month): ConsumptionMonthData
{
    return collect($months)->firstOrFail(fn (ConsumptionMonthData $data): bool => $data->month === $month);
}

/**
 * The service.
 */
function consumptionTestService(): Consumption
{
    return new Consumption;
}

it('splits the bill of the example among its months', function (): void {
    $rows = collect([consumptionTestRow('2026-09-15', '2026-11-14', 366, 128.10, id: 9)]);

    $months = consumptionTestService()->months($rows, CarbonImmutable::parse('2025-12-01'), CarbonImmutable::parse('2026-11-01'));

    expect($months)->toHaveCount(12)
        ->and($months[0]->month)->toBe('2025-12')
        ->and($months[11]->month)->toBe('2026-11');

    $september = consumptionTestMonth($months, '2026-09');
    $october = consumptionTestMonth($months, '2026-10');
    $november = consumptionTestMonth($months, '2026-11');

    expect($september->quantity)->toBe(96.0)->and($september->amount)->toBe(33.6)->and($september->coverage)->toBe('partial')->and($september->coveredUntil)->toBeNull()
        ->and($october->quantity)->toBe(186.0)->and($october->amount)->toBe(65.1)->and($october->coverage)->toBe('complete')->and($october->billIds)->toBe([9])
        ->and($october->price)->toBe(0.35)
        ->and($november->quantity)->toBe(84.0)->and($november->amount)->toBe(29.4)->and($november->coverage)->toBe('partial')->and($november->coveredUntil)->toBe(14);
});

it('keeps the total of a bill across a leap February to the cent', function (): void {
    $rows = collect([consumptionTestRow('2027-12-20', '2028-02-19', 100, 33.33)]);

    $months = consumptionTestService()->months($rows, CarbonImmutable::parse('2027-12-01'), CarbonImmutable::parse('2028-02-01'));

    expect($months)->toHaveCount(3)
        ->and(round(array_sum(array_map(fn (ConsumptionMonthData $month): float => (float) $month->amount, $months)), 2))->toBe(33.33)
        ->and(round(array_sum(array_map(fn (ConsumptionMonthData $month): float => (float) $month->quantity, $months)), 3))->toBe(100.0)
        ->and($months[1]->coverage)->toBe('complete');
});

it('leaves a month without bills empty', function (): void {
    $rows = collect([consumptionTestRow('2026-09-01', '2026-09-30', 100, 30)]);

    $months = consumptionTestService()->months($rows, CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-09-01'));

    expect($months[0]->quantity)->toBeNull()->and($months[0]->amount)->toBeNull()->and($months[0]->price)->toBeNull()
        ->and($months[0]->coverage)->toBe('none')->and($months[0]->billIds)->toBe([]);
});

it('gives each month the bill of the year before', function (): void {
    $rows = collect([consumptionTestRow('2025-09-01', '2025-09-30', 80, 24), consumptionTestRow('2026-09-01', '2026-09-30', 100, 30, id: 2)]);

    $months = consumptionTestService()->months($rows, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-01'));

    expect($months[0]->previousQuantity)->toBe(80.0)->and($months[0]->previousAmount)->toBe(24.0)->and($months[0]->billIds)->toBe([2]);
});

it('marks the months of an estimated bill and of one with adjustments', function (): void {
    $rows = collect([consumptionTestRow('2026-08-01', '2026-09-30', 100, 30, estimated: true), consumptionTestRow('2026-10-01', '2026-10-31', 50, 15, adjusted: true, id: 2)]);

    $months = consumptionTestService()->months($rows, CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-10-01'));

    expect($months[0]->estimated)->toBeTrue()->and($months[1]->estimated)->toBeTrue()->and($months[2]->estimated)->toBeFalse()
        ->and($months[0]->adjusted)->toBeFalse()->and($months[2]->adjusted)->toBeTrue();
});

it('adds a credit adjustment and leaves the month without a price', function (): void {
    $rows = collect([consumptionTestRow('2026-09-01', '2026-09-30', 30, 10), consumptionTestRow('2026-09-01', '2026-09-30', -50, -12, adjusted: true, id: 2)]);

    $month = consumptionTestService()->months($rows, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-01'))[0];

    expect($month->quantity)->toBe(-20.0)->and($month->price)->toBeNull()->and($month->billIds)->toBe([1, 2]);
});

it('does not divide by zero for a period of a single day', function (): void {
    $rows = collect([consumptionTestRow('2026-09-10', '2026-09-10', 5, 2)]);

    $month = consumptionTestService()->months($rows, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-01'))[0];

    expect($month->quantity)->toBe(5.0)->and($month->amount)->toBe(2.0)->and($month->coverage)->toBe('partial')->and($month->coveredUntil)->toBe(10);
});

it('works out the four figures of the board', function (): void {
    $rows = consumptionTestMonthly('2025-10-01', array_slice(consumptionTestBianchi(), 12), unpaid: 11);

    $figures = consumptionTestService()->figures($rows, CarbonImmutable::parse('2025-10-01'), CarbonImmutable::parse('2026-09-01'));

    expect($figures->quantity)->toBe(2711.0)
        ->and($figures->amount)->toBe(853.1)
        ->and($figures->bills)->toBe(12)
        ->and($figures->toPay)->toBe(1)
        ->and(round((float) $figures->averagePrice, 2))->toBe(0.31)
        ->and($figures->coveredMonths)->toBe(12)
        ->and($figures->previousQuantity)->toBeNull()
        ->and($figures->dearestMonth)->toBe('2026-09')
        ->and($figures->dearestPrice)->toBe(0.3883);
});

it('compares the 12 months with the year before when both are covered', function (): void {
    $rows = consumptionTestMonthly('2024-10-01', consumptionTestBianchi());

    $figures = consumptionTestService()->figures($rows, CarbonImmutable::parse('2025-10-01'), CarbonImmutable::parse('2026-09-01'));

    expect($figures->previousQuantity)->toBe(2493.0)->and($figures->bills)->toBe(12)->and($figures->toPay)->toBe(0);
});

it('does not compare when only 9 months have data', function (): void {
    $rows = consumptionTestMonthly('2024-10-01', array_slice(consumptionTestBianchi(), 0, 12))
        ->merge(consumptionTestMonthly('2026-01-01', array_slice(consumptionTestBianchi(), 15)));

    $figures = consumptionTestService()->figures($rows, CarbonImmutable::parse('2025-10-01'), CarbonImmutable::parse('2026-09-01'));

    expect($figures->coveredMonths)->toBe(9)->and($figures->previousQuantity)->toBeNull();
});

it('lists the bills of the 12 months from the latest', function (): void {
    $rows = consumptionTestMonthly('2025-09-01', [[100, 30], [100, 30], [100, 45], [100, 30]], unpaid: 3);

    $bills = consumptionTestService()->bills($rows, CarbonImmutable::parse('2025-10-01'), CarbonImmutable::parse('2025-12-01'), fn (UtilityConsumption $row): bool => $row->id === 3);

    expect($bills)->toHaveCount(3)
        ->and($bills[0]->id)->toBe(4)->and($bills[0]->period_start)->toBe('2025-12-01')->and($bills[0]->paid)->toBeFalse()->and($bills[0]->document_id)->toBe(1004)
        ->and($bills[0]->unit)->toBe('kwh')->and($bills[0]->source)->toBe('reading')
        ->and($bills[1]->id)->toBe(3)->and($bills[1]->above_average)->toBeTrue()->and($bills[1]->price)->toBe(0.45)->and($bills[1]->can_update)->toBeTrue()->and($bills[1]->can_delete)->toBeFalse()
        ->and($bills[2]->above_average)->toBeFalse()->and($bills[2]->paid)->toBeTrue()->and($bills[2]->check)->toBeFalse()->and($bills[2]->can_update)->toBeFalse();
});

it('flags a bill with a price out of the usual range', function (): void {
    $rows = collect([consumptionTestRow('2026-09-01', '2026-09-30', 10, 300)]);

    expect(consumptionTestService()->bills($rows, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-01'))[0]->check)->toBeTrue();
});

it('finds the first and the last month with data', function (): void {
    $rows = collect([consumptionTestRow('2026-09-15', '2026-11-14', 366, 128.10), consumptionTestRow('2025-02-10', '2025-03-09', 10, 3, id: 2)]);

    expect(consumptionTestService()->lastMonthWithData($rows)?->toDateString())->toBe('2026-11-01')
        ->and(consumptionTestService()->firstMonthWithData($rows)?->toDateString())->toBe('2025-02-01')
        ->and(consumptionTestService()->lastMonthWithData(collect()))->toBeNull();
});

/**
 * 24 flat months (100 kWh, 30 €) up to September 2026, with the last month changed.
 *
 * @param  array{float, float}  $september
 * @param  list<int>  $estimated
 * @return Collection<int, UtilityConsumption>
 */
function consumptionTestFlat(array $september = [100, 30], array $estimated = []): Collection
{
    $months = array_fill(0, 24, [100.0, 30.0]);
    $months[23] = $september;

    return consumptionTestMonthly('2024-10-01', $months, $estimated);
}

/**
 * The sentence of the 12 months up to September 2026.
 *
 * @param  Collection<int, UtilityConsumption>  $rows
 */
function consumptionTestSentence(Collection $rows, string $today = '2026-10-10', string $locale = 'en', UtilityType $type = UtilityType::Electricity): ?string
{
    $service = consumptionTestService();
    $months = $service->months($rows, CarbonImmutable::parse('2025-10-01'), CarbonImmutable::parse('2026-09-01'));

    return $service->sentence($rows, $months, $type, CarbonImmutable::parse($today), $locale);
}

it('says the price went up when the consumption did not', function (): void {
    expect(consumptionTestSentence(consumptionTestFlat([100, 40])))
        ->toBe("In September you paid the most for electricity this year: 0,40\u{a0}€ per kWh against an average of 0,31\u{a0}€. Your consumption did not go up: the price changed.")
        ->and(consumptionTestSentence(consumptionTestFlat([100, 40]), locale: 'it'))
        ->toBe("A settembre hai pagato la luce più cara dell'anno: 0,40\u{a0}€ a kWh contro una media di 0,31\u{a0}€. Il consumo non è salito: è cambiato il prezzo.")
        ->and(consumptionTestSentence(consumptionTestFlat([100, 40]), locale: 'it', type: UtilityType::Water))
        ->toStartWith('A settembre hai pagato l\'acqua più cara dell\'anno');
});

it('says the consumption went up on the year before', function (): void {
    expect(consumptionTestSentence(consumptionTestFlat([120, 36])))->toBe('In September you used 120 kWh, 20 more than September 2025.')
        ->and(consumptionTestSentence(consumptionTestFlat([1290.5, 387.15]), locale: 'it'))->toBe('A settembre hai consumato 1.290,5 kWh, 1.190,5 più di settembre 2025.');
});

it('says the last two bills are estimated', function (): void {
    expect(consumptionTestSentence(consumptionTestFlat(estimated: [22, 23])))
        ->toBe('The last two bills are estimated. Send the meter reading to the supplier, so you pay for what you really use.');
});

it('says a bill is missing when more days than usual went by', function (): void {
    expect(consumptionTestSentence(consumptionTestFlat(), today: '2026-12-15'))->toBe('The bill after 30/09/2026 is missing.')
        ->and(consumptionTestSentence(consumptionTestFlat(), today: '2026-12-15', locale: 'it'))->toBe('Manca la bolletta dopo il 30/09/2026.');
});

it('says nothing when nothing stands out', function (): void {
    expect(consumptionTestSentence(consumptionTestFlat()))->toBeNull()
        ->and(consumptionTestSentence(collect()))->toBeNull();
});

it('follows the rules in their order', function (): void {
    // the price rule wins over the estimated bills, the consumption rule too, and the estimated bills over the missing bill
    expect(consumptionTestSentence(consumptionTestFlat([100, 40], [22, 23])))->toStartWith('In September you paid the most')
        ->and(consumptionTestSentence(consumptionTestFlat([120, 36], [22, 23])))->toStartWith('In September you used')
        ->and(consumptionTestSentence(consumptionTestFlat(estimated: [22, 23]), today: '2026-12-15'))->toStartWith('The last two bills are estimated.');
});

it('quotes in the sentence the same average as the figures, with a bill without an amount in a month', function (): void {
    // a hand-written row without an amount beside the priced bill of August: only the priced bills make the average
    $rows = consumptionTestFlat([100, 40])->push(consumptionTestRow('2026-08-01', '2026-08-31', 400, null, id: 99));
    $from = CarbonImmutable::parse('2025-10-01');
    $to = CarbonImmutable::parse('2026-09-01');

    $average = consumptionTestService()->figures($rows, $from, $to)->averagePrice;

    expect($average)->toBe(0.3083)
        ->and(consumptionTestSentence($rows))
        ->toBe("In September you paid the most for electricity this year: 0,40\u{a0}€ per kWh against an average of 0,31\u{a0}€. Your consumption did not go up: the price changed.");
});
