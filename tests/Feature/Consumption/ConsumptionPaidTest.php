<?php

declare(strict_types=1);

use App\Data\Consumption\ConsumptionBillData;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Family;
use App\Models\Utility;
use App\Models\UtilityConsumption;
use App\Services\Consumption;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->family = Family::factory()->create();
    CurrentFamily::set($this->family->id);
});

afterEach(fn () => CurrentFamily::clear());

test('a bill is paid when its document is linked to an expense, read without a query per row', function (): void {
    $utility = Utility::factory()->for($this->family)->create();
    $paid = Document::factory()->for($this->family)->create();
    $unpaid = Document::factory()->for($this->family)->create();
    Expense::factory()->for($this->family)->create()->documents()->attach($paid->id);
    UtilityConsumption::factory()->for($this->family)->for($utility)->create(['document_id' => $paid->id, 'period_start' => '2026-08-01', 'period_end' => '2026-08-31']);
    UtilityConsumption::factory()->for($this->family)->for($utility)->create(['document_id' => $unpaid->id]);
    UtilityConsumption::factory()->for($this->family)->for($utility)->create(['document_id' => null, 'period_start' => '2026-07-01', 'period_end' => '2026-07-31']);

    $rows = UtilityConsumption::query()->with(Consumption::relations())->get();

    DB::enableQueryLog();
    $figures = (new Consumption)->figures($rows, CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-09-01'));
    $bills = (new Consumption)->bills($rows, CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-09-01'));

    expect(DB::getQueryLog())->toBe([])
        ->and($figures->bills)->toBe(3)
        ->and($figures->toPay)->toBe(1)
        ->and(array_map(fn (ConsumptionBillData $bill): bool => $bill->paid, $bills))->toBe([false, true, true]);
});

test('rows with a document but without the relations are refused', function (): void {
    $document = Document::factory()->for($this->family)->create();
    UtilityConsumption::factory()->for($this->family)->create(['document_id' => $document->id]);

    (new Consumption)->figures(UtilityConsumption::query()->get(), CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-01'));
})->throws(LogicException::class);
