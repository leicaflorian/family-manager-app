<?php

declare(strict_types=1);

use App\Enums\ReminderStatus;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedIncome;
use App\Models\Reminder;
use App\Models\User;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-11 10:00', 'Europe/Rome'));
});

afterEach(fn () => Date::setTestNow());

/**
 * The props of the Agenda as an account sees them.
 *
 * @param  User  $user  the account
 * @param  string  $query  the query string, for example `?view=calendar`
 * @return array<string, mixed>
 */
function calendarProps(User $user, string $query = '?view=calendar'): array
{
    return test()->actingAs($user)->get('/agenda'.$query)->assertOk()->viewData('page')['props'];
}

test('month range runs from the Monday before to the Sunday after', function (): void {
    $calendar = calendarProps(registerFamily(), '?view=calendar&month=2026-11')['calendar'];

    expect($calendar['month'])->toBe('2026-11')
        ->and($calendar['from'])->toBe('2026-10-26')
        ->and($calendar['to'])->toBe('2026-12-06')
        ->and($calendar['today'])->toBe('2026-11-11');
});

test('a wrong month shows the current one', function (string $month): void {
    $props = calendarProps(registerFamily(), '?view=calendar&month='.$month);

    expect($props['calendar']['month'])->toBe('2026-11');
})->with(['2026-13', 'abc', '2026-1']);

test('open entries of the month are the list ones inside the grid', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, function (): void {
        agendaPlanned(null, 'Bolletta luce', 50, '2026-11-10', ['is_recurring' => false]);
        agendaPlanned(null, 'Affitto di dicembre', 700, '2026-12-20', ['is_recurring' => false]);
        PlannedIncome::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Stipendio', 'amount' => 1850, 'start_date' => '2026-11-12', 'is_recurring' => false]);
        Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'is_automatic' => false, 'title' => 'Chiamare l\'idraulico', 'due_date' => '2026-11-20', 'status' => ReminderStatus::Open]);
    });

    $calendar = calendarProps($marco)['calendar'];

    expect(array_column($calendar['entries'], 'title'))->toBe(['Bolletta luce', 'Chiamare l\'idraulico'])
        ->and(array_column($calendar['entries'], 'kind'))->toBe(['payment', 'task'])
        ->and($calendar['done'])->toBe([]);
});

test('done entries are paid payments and done reminders', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, function (): void {
        $paid = agendaPlanned(null, 'Bolletta gas', 80, '2026-11-05', ['is_recurring' => false]);
        Expense::factory()->create(['family_id' => CurrentFamily::id(), 'planned_expense_id' => $paid->id, 'occurrence_date' => '2026-11-05', 'amount' => 80, 'date' => '2026-11-06']);
        $income = PlannedIncome::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Stipendio', 'amount' => 1850, 'start_date' => '2026-11-07', 'is_recurring' => false]);
        Income::factory()->create(['family_id' => CurrentFamily::id(), 'planned_income_id' => $income->id, 'occurrence_date' => '2026-11-07', 'amount' => 1850, 'date' => '2026-11-07']);
        Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'is_automatic' => false, 'title' => 'Telefonata fatta', 'due_date' => '2026-11-03', 'status' => ReminderStatus::Done]);
        Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'is_automatic' => false, 'title' => 'Fatta e rimandata', 'due_date' => '2026-10-01', 'postponed_to' => '2026-11-09', 'status' => ReminderStatus::Done]);
        Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'is_automatic' => false, 'title' => 'Fatta a ottobre', 'due_date' => '2026-10-01', 'status' => ReminderStatus::Done]);
    });

    $calendar = calendarProps($marco)['calendar'];

    expect($calendar['entries'])->toBe([])
        ->and(array_column($calendar['done'], 'title'))->toBe(['Telefonata fatta', 'Bolletta gas', 'Fatta e rimandata'])
        ->and(array_column($calendar['done'], 'date'))->toBe(['2026-11-03', '2026-11-05', '2026-11-09']);
});

test('a past month only shows what is still late', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, function (): void {
        agendaPlanned(null, 'Bolletta in ritardo', 50, '2026-09-10', ['is_recurring' => false]);
        $paid = agendaPlanned(null, 'Bolletta pagata', 60, '2026-09-12', ['is_recurring' => false]);
        Expense::factory()->create(['family_id' => CurrentFamily::id(), 'planned_expense_id' => $paid->id, 'occurrence_date' => '2026-09-12', 'amount' => 60, 'date' => '2026-09-13']);
    });

    $calendar = calendarProps($marco, '?view=calendar&month=2026-09')['calendar'];

    expect($calendar['from'])->toBe('2026-08-31')
        ->and(array_column($calendar['entries'], 'title'))->toBe(['Bolletta in ritardo'])
        ->and(array_column($calendar['done'], 'title'))->toBe(['Bolletta pagata']);
});

test('the calendar view is remembered', function (): void {
    $marco = registerFamily();

    expect(calendarProps($marco, '')['agenda']['view'])->toBe('todo');

    calendarProps($marco);
    expect($marco->fresh()->agenda_filters['calendar'])->toBeTrue()
        ->and(calendarProps($marco->fresh(), '')['agenda']['view'])->toBe('calendar')
        ->and(calendarProps($marco->fresh(), '')['agenda']['filters']['calendar'])->toBeTrue();

    // The done view is not remembered
    expect(calendarProps($marco->fresh(), '?view=done')['agenda']['view'])->toBe('done')
        ->and(calendarProps($marco->fresh(), '')['agenda']['view'])->toBe('calendar');

    calendarProps($marco->fresh(), '?view=todo');
    expect(calendarProps($marco->fresh(), '')['agenda']['view'])->toBe('todo');
});

test('calendar prop is null outside the calendar view', function (): void {
    $marco = registerFamily();

    expect(calendarProps($marco, '')['calendar'])->toBeNull()
        ->and(calendarProps($marco, '?view=done')['calendar'])->toBeNull();
});

test('saving the other filters keeps the remembered view', function (): void {
    $marco = registerFamily();
    calendarProps($marco);

    $this->actingAs($marco->fresh())->put('/agenda/filters', ['months' => 3, 'person_id' => null, 'hidden_areas' => []])->assertRedirect();

    expect($marco->fresh()->agenda_filters['calendar'])->toBeTrue();
});
