<?php

declare(strict_types=1);

use App\Actions\Budget\RecordPayment;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Services\Deadlines;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-27 10:00', 'Europe/Rome'));
});

/**
 * Builds a family with the electricity and gas planned expenses and one bill that waits on both: electricity in the
 * column, gas in the table. Runs the callback inside that family.
 *
 * @param  Closure(int, PlannedExpense, PlannedExpense, Document): mixed  $callback  Family id, electricity, gas, bill.
 */
function waitsOnPlansIn(Closure $callback): mixed
{
    $familyId = Family::factory()->create()->id;

    return CurrentFamily::using($familyId, function () use ($familyId, $callback): mixed {
        $electricity = PlannedExpense::factory()->create(['family_id' => $familyId, 'title' => 'Luce', 'amount' => 72, 'is_estimated' => true, 'start_date' => '2026-01-23']);
        $gas = PlannedExpense::factory()->create(['family_id' => $familyId, 'title' => 'Gas', 'amount' => 55, 'is_estimated' => true, 'start_date' => '2026-10-30']);
        $bill = Document::factory()->create(['family_id' => $familyId, 'title' => 'Bolletta luce e gas novembre 2026', 'date' => '2026-11-20', 'planned_expense_id' => $electricity->id]);
        $bill->waitOn($gas);

        return $callback($familyId, $electricity, $gas, $bill);
    });
}

test('a bill waiting on two planned expenses shows in the due date of each', function (): void {
    waitsOnPlansIn(function (int $familyId, PlannedExpense $electricity, PlannedExpense $gas): void {
        $deadlines = resolve(Deadlines::class);

        expect($deadlines->find($electricity, CarbonImmutable::parse('2026-11-23'))->title)->toBe('Bolletta luce e gas novembre 2026')
            ->and($deadlines->find($gas, CarbonImmutable::parse('2026-11-30'))->title)->toBe('Bolletta luce e gas novembre 2026');
    });
});

test('paying the gas due date moves the bill to the new expense and electricity still waits on it', function (): void {
    waitsOnPlansIn(function (int $familyId, PlannedExpense $electricity, PlannedExpense $gas, Document $bill): void {
        $account = FinancialAccount::factory()->create(['family_id' => $familyId]);
        $deadline = resolve(Deadlines::class)->find($gas, CarbonImmutable::parse('2026-11-30'));

        $expense = resolve(RecordPayment::class)->handle($deadline, CarbonImmutable::parse('2026-11-21'), 69.40, $account->id, null);

        expect($expense->documents()->pluck('documents.id')->all())->toBe([$bill->id])
            ->and(resolve(Deadlines::class)->find($electricity, CarbonImmutable::parse('2026-11-23'))->title)->toBe('Bolletta luce e gas novembre 2026')
            ->and(Document::query()->waitingFor([$electricity->id])->pluck('id')->all())->toBe([$bill->id]);
    });
});

test('paying electricity after gas moves the bill to the electricity expense too', function (): void {
    waitsOnPlansIn(function (int $familyId, PlannedExpense $electricity, PlannedExpense $gas, Document $bill): void {
        $account = FinancialAccount::factory()->create(['family_id' => $familyId]);
        $pay = resolve(RecordPayment::class);
        $gasExpense = $pay->handle(resolve(Deadlines::class)->find($gas, CarbonImmutable::parse('2026-11-30')), CarbonImmutable::parse('2026-11-21'), 69.40, $account->id, null);

        $electricityExpense = $pay->handle(resolve(Deadlines::class)->find($electricity, CarbonImmutable::parse('2026-11-23')), CarbonImmutable::parse('2026-11-21'), 96.25, $account->id, null);
        $again = $pay->handle(resolve(Deadlines::class)->find($gas, CarbonImmutable::parse('2026-12-30')), CarbonImmutable::parse('2026-12-21'), 55, $account->id, null);

        expect($electricityExpense->documents()->pluck('documents.id')->all())->toBe([$bill->id])
            ->and($gasExpense->documents()->pluck('documents.id')->all())->toBe([$bill->id])
            ->and($again->documents()->count())->toBe(0);
    });
});

test('a document waiting on one planned expense and already paid is not moved again', function (): void {
    waitsOnPlansIn(function (int $familyId, PlannedExpense $electricity, PlannedExpense $gas): void {
        $account = FinancialAccount::factory()->create(['family_id' => $familyId]);
        $pay = resolve(RecordPayment::class);
        $single = Document::factory()->create(['family_id' => $familyId, 'date' => '2026-11-25', 'planned_expense_id' => $gas->id]);
        $single->expenses()->attach(Expense::factory()->create(['family_id' => $familyId])->id);

        $expense = $pay->handle(resolve(Deadlines::class)->find($gas, CarbonImmutable::parse('2026-11-30')), CarbonImmutable::parse('2026-11-21'), 69.40, $account->id, null);

        expect($expense->documents()->pluck('documents.id')->all())->not->toContain($single->id);
    });
});

test('waiting on the same planned expense twice adds no row', function (): void {
    waitsOnPlansIn(function (int $familyId, PlannedExpense $electricity, PlannedExpense $gas, Document $bill): void {
        $bill->waitOn($gas);
        $bill->waitOn($gas);
        $bill->waitOn($electricity);

        expect($bill->waitingPlans()->count())->toBe(1)
            ->and($bill->refresh()->planned_expense_id)->toBe($electricity->id);
    });
});

test('a document without a wait writes only the column', function (): void {
    waitsOnPlansIn(function (int $familyId, PlannedExpense $electricity, PlannedExpense $gas): void {
        $other = Document::factory()->create(['family_id' => $familyId]);

        $other->waitOn($gas);

        expect($other->refresh()->planned_expense_id)->toBe($gas->id)
            ->and($other->waitingPlans()->count())->toBe(0);
    });
});

test('a bill of another family never shows among the waiting ones', function (): void {
    $otherId = Family::factory()->create()->id;
    CurrentFamily::using($otherId, function () use ($otherId): void {
        $gas = PlannedExpense::factory()->create(['family_id' => $otherId, 'title' => 'Gas']);
        Document::factory()->create(['family_id' => $otherId, 'planned_expense_id' => $gas->id])->waitOn(PlannedExpense::factory()->create(['family_id' => $otherId]));
    });

    waitsOnPlansIn(function (int $familyId, PlannedExpense $electricity, PlannedExpense $gas, Document $bill): void {
        expect(Document::query()->waitingFor([$electricity->id, $gas->id])->pluck('id')->all())->toBe([$bill->id]);
    });
});
