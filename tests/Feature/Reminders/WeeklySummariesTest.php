<?php

declare(strict_types=1);

use App\Data\AgendaEntryData;
use App\Enums\ExceptionType;
use App\Enums\ReminderStatus;
use App\Models\Expense;
use App\Models\Family;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Models\PlannedIncome;
use App\Models\Reminder;
use App\Models\Vehicle;
use App\Services\Reminders\WeeklySummaries;
use App\Support\CurrentFamily;
use App\Support\Reminders\WeeklySummary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-23 06:00', 'Europe/Rome'));
    CurrentFamily::set(Family::factory()->create()->id);
});

afterEach(function (): void {
    Date::setTestNow();
    CurrentFamily::clear();
});

/**
 * A one-off planned expense of the family.
 *
 * @param  string  $title  the title
 * @param  string  $date  the due day, `yyyy-mm-dd`
 * @param  float  $amount  the amount
 * @param  bool  $estimated  whether the amount is an estimate
 */
function weeklySummariesExpense(string $title, string $date, float $amount, bool $estimated = false): PlannedExpense
{
    return PlannedExpense::factory()->create([
        'family_id' => CurrentFamily::id(),
        'title' => $title,
        'amount' => $amount,
        'is_recurring' => false,
        'is_estimated' => $estimated,
        'start_date' => $date,
    ]);
}

/**
 * The summary of the week that starts on Monday 23/11/2026.
 */
function weeklySummariesWeek(): WeeklySummary
{
    return resolve(WeeklySummaries::class)->week(CarbonImmutable::parse('2026-11-23'));
}

/**
 * @param  list<AgendaEntryData>  $entries
 * @return list<string>
 */
function weeklySummariesTitles(array $entries): array
{
    return array_map(static fn (AgendaEntryData $entry): string => $entry->title, $entries);
}

test('the unpaid planned expenses of the week are to pay, with the total', function (): void {
    weeklySummariesExpense('Bolletta luce', '2026-11-25', 34.90);
    weeklySummariesExpense('Bolletta gas', '2026-11-26', 6.99);

    $week = weeklySummariesWeek();

    expect(weeklySummariesTitles($week->toPay))->toBe(['Bolletta luce', 'Bolletta gas'])
        ->and($week->late)->toBe([])
        ->and($week->total)->toBe(41.89);
});

test('a payment of last week still unpaid is late and counts in the total', function (): void {
    weeklySummariesExpense('Affitto', '2026-11-20', 76.20);
    weeklySummariesExpense('Bolletta luce', '2026-11-25', 10.00);

    $week = weeklySummariesWeek();

    expect(weeklySummariesTitles($week->late))->toBe(['Affitto'])
        ->and(weeklySummariesTitles($week->toPay))->toBe(['Bolletta luce'])
        ->and($week->total)->toBe(86.2);
});

test('a paid or skipped due date and the incomes are not there', function (): void {
    $paid = weeklySummariesExpense('Pagata', '2026-11-24', 20.00);
    Expense::factory()->create(['family_id' => CurrentFamily::id(), 'planned_expense_id' => $paid->id, 'occurrence_date' => '2026-11-24', 'amount' => 20.00, 'date' => '2026-11-23']);
    $skipped = weeklySummariesExpense('Saltata', '2026-11-25', 30.00);
    PlannedExpenseException::factory()->create(['family_id' => CurrentFamily::id(), 'planned_expense_id' => $skipped->id, 'type' => ExceptionType::Skip, 'occurrence_date' => '2026-11-25']);
    PlannedIncome::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Stipendio', 'is_recurring' => false, 'start_date' => '2026-11-27']);

    $week = weeklySummariesWeek();

    expect($week->late)->toBe([])
        ->and($week->toPay)->toBe([])
        ->and($week->tasks)->toBe([])
        ->and($week->isEmpty())->toBeTrue();
});

test('nothing after Sunday', function (): void {
    weeklySummariesExpense('Dopo', '2026-11-30', 50.00);

    expect(weeklySummariesWeek()->isEmpty())->toBeTrue();
});

test('deadlines without an amount and open tasks are reminders', function (): void {
    $panda = Vehicle::factory()->create(['family_id' => CurrentFamily::id(), 'name' => 'Panda', 'inspection_due_date' => '2026-11-26']);
    // The vehicle was born with its automatic reminder of the inspection
    $automatic = Reminder::query()->where('source_field', 'vehicles.inspection_due_date')->sole();
    $task = Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Dentista', 'is_automatic' => false, 'due_date' => '2026-11-24', 'status' => ReminderStatus::Open]);

    $week = weeklySummariesWeek();

    expect($week->tasks)->toHaveCount(2)
        ->and($week->toPay)->toBe([])
        ->and($week->reminderIds())->toEqualCanonicalizing([$automatic->id, $task->id])
        ->and($week->isEmpty())->toBeFalse();
});

test('a late task is late', function (): void {
    $task = Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Vecchio impegno', 'is_automatic' => false, 'due_date' => '2026-11-18', 'status' => ReminderStatus::Open]);

    $week = weeklySummariesWeek();

    expect(weeklySummariesTitles($week->late))->toBe(['Vecchio impegno'])
        ->and($week->tasks)->toBe([])
        ->and($week->total)->toBe(0.0)
        ->and($week->reminderIds())->toBe([$task->id]);
});

test('an estimated amount marks the total', function (): void {
    weeklySummariesExpense('Bolletta luce', '2026-11-25', 40.00, estimated: true);

    expect(weeklySummariesWeek()->estimated)->toBeTrue();
});

test('an empty week is empty', function (): void {
    $week = weeklySummariesWeek();

    expect($week->isEmpty())->toBeTrue()
        ->and($week->total)->toBe(0.0)
        ->and($week->estimated)->toBeFalse()
        ->and($week->reminderIds())->toBe([]);
});
