<?php

declare(strict_types=1);

use App\Actions\Budget\RecordPayment;
use App\Enums\ReminderStatus;
use App\Enums\VehicleStatus;
use App\Models\InsurancePolicy;
use App\Models\PlannedExpense;
use App\Models\Reminder;
use App\Models\Vehicle;
use App\Services\Agenda;
use App\Services\Deadlines;
use App\Services\Reminders\CardNotices;
use App\Services\Reminders\MissingExpenses;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->user = registerFamily();
});

afterEach(fn () => Date::setTestNow());

/**
 * An open automatic reminder linked to a card.
 *
 * @param  Model  $card  the card
 * @param  string  $field  the `source_field`
 * @param  string  $due  the due date
 */
function plannedDeadlineReminder(Model $card, string $field, string $due = '2027-01-31'): Reminder
{
    $reminder = Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'is_automatic' => true, 'source_field' => $field, 'due_date' => $due]);
    DB::table('remindables')->insert(['reminder_id' => $reminder->id, 'remindable_type' => $card->getMorphClass(), 'remindable_id' => $card->id]);

    return $reminder->load('link');
}

/**
 * A vehicle with both deadlines on the same day.
 */
function plannedDeadlineVehicle(): Vehicle
{
    return Vehicle::factory()->create(['family_id' => CurrentFamily::id(), 'status' => VehicleStatus::Active, 'road_tax_due_date' => '2027-01-31', 'inspection_due_date' => '2027-01-31']);
}

test('a vehicle with the road tax expense still misses the inspection expense', function (): void {
    insideFamilyOf($this->user, function (): void {
        $vehicle = plannedDeadlineVehicle();
        $tax = plannedDeadlineReminder($vehicle, 'vehicles.road_tax_due_date');
        $inspection = plannedDeadlineReminder($vehicle, 'vehicles.inspection_due_date');
        agendaPlanned($vehicle, 'Bollo', 180, '2026-01-31', ['deadline_field' => 'vehicles.road_tax_due_date']);

        expect(resolve(MissingExpenses::class)->of(collect([$tax, $inspection]), CarbonImmutable::today('Europe/Rome')))->toBe([$inspection->id => true]);
    });
});

test('a planned expense of the vehicle without a deadline covers neither the road tax nor the inspection', function (): void {
    insideFamilyOf($this->user, function (): void {
        $vehicle = plannedDeadlineVehicle();
        $tax = plannedDeadlineReminder($vehicle, 'vehicles.road_tax_due_date');
        $inspection = plannedDeadlineReminder($vehicle, 'vehicles.inspection_due_date');
        agendaPlanned($vehicle, 'Carburante', 80, '2026-01-31');

        expect(resolve(MissingExpenses::class)->of(collect([$tax, $inspection]), CarbonImmutable::today('Europe/Rome')))->toBe([$tax->id => true, $inspection->id => true]);
    });
});

test('a policy stays covered by any planned expense of the policy', function (): void {
    insideFamilyOf($this->user, function (): void {
        $policy = InsurancePolicy::factory()->create(['family_id' => CurrentFamily::id()]);
        $reminder = plannedDeadlineReminder($policy, 'insurance_policies.expiry_date');
        agendaPlanned($policy, 'Rata', 100, '2026-01-31');

        expect(resolve(MissingExpenses::class)->of(collect([$reminder]), CarbonImmutable::today('Europe/Rome')))->toBe([]);
    });
});

test('paying the road tax expense closes only the road tax reminder, though the inspection falls on the same day', function (): void {
    insideFamilyOf($this->user, function (): void {
        $vehicle = plannedDeadlineVehicle();
        $tax = plannedDeadlineReminder($vehicle, 'vehicles.road_tax_due_date');
        $inspection = plannedDeadlineReminder($vehicle, 'vehicles.inspection_due_date');
        $planned = agendaPlanned($vehicle, 'Bollo', 180, '2026-01-31', ['deadline_field' => 'vehicles.road_tax_due_date']);
        $due = resolve(Deadlines::class)->find($planned, CarbonImmutable::parse('2027-01-31'));

        resolve(RecordPayment::class)->handle($due, CarbonImmutable::parse('2027-01-31'), 180.0, null, null);

        expect($tax->fresh()->status)->toBe(ReminderStatus::Done)
            ->and($inspection->fresh()->status)->toBe(ReminderStatus::Open);
    });
});

test('paying a planned expense of the vehicle without a deadline closes neither reminder', function (): void {
    insideFamilyOf($this->user, function (): void {
        $vehicle = plannedDeadlineVehicle();
        $tax = plannedDeadlineReminder($vehicle, 'vehicles.road_tax_due_date');
        $planned = agendaPlanned($vehicle, 'Carburante', 80, '2026-01-31');
        $due = resolve(Deadlines::class)->find($planned, CarbonImmutable::parse('2027-01-31'));

        resolve(RecordPayment::class)->handle($due, CarbonImmutable::parse('2027-01-31'), 80.0, null, null);

        expect($tax->fresh()->status)->toBe(ReminderStatus::Open);
    });
});

test('the Agenda joins the inspection expense only to the inspection deadline', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2027-01-20 10:00', 'Europe/Rome'));

    insideFamilyOf($this->user, function (): void {
        $vehicle = plannedDeadlineVehicle();
        agendaPlanned($vehicle, 'Revisione', 80, '2025-01-31', ['deadline_field' => 'vehicles.inspection_due_date']);

        $entries = resolve(Agenda::class)->entries(CarbonImmutable::parse('2027-01-20', 'Europe/Rome'), CarbonImmutable::parse('2027-02-10', 'Europe/Rome'));
        $found = collect($entries)->filter(fn ($entry): bool => $entry->date === '2027-01-31');

        // The payment carries the inspection; the road tax stays its own deadline
        expect($found->map(fn ($entry): array => [$entry->kind, $entry->title])->sort()->values()->all())->toBe([['deadline', 'Road tax'], ['payment', 'Inspection']]);
    });
});

test('a vehicle gets no notice about the missing planned expense, a policy does', function (): void {
    insideFamilyOf($this->user, function (): void {
        $today = CarbonImmutable::today('Europe/Rome');
        $vehicle = plannedDeadlineVehicle();
        plannedDeadlineReminder($vehicle, 'vehicles.road_tax_due_date', '2026-11-30');
        $policy = InsurancePolicy::factory()->create(['family_id' => CurrentFamily::id()]);
        plannedDeadlineReminder($policy, 'insurance_policies.expiry_date', '2026-11-30');

        $titles = fn (Model $card): array => collect(resolve(CardNotices::class)->for($card->fresh(), $today))->pluck('title')->all();

        expect($titles($vehicle))->not->toContain('The planned expense is missing')
            ->and($titles($policy))->toContain('The planned expense is missing');
    });
});

test('Add the expense opens the form filled in for the chosen deadline', function (string $field, string $title, int $interval): void {
    $vehicle = insideFamilyOf($this->user, fn (): Vehicle => Vehicle::factory()->create(['family_id' => CurrentFamily::id(), 'road_tax_due_date' => '2027-01-31', 'inspection_due_date' => '2027-03-21']));
    $date = $field === 'vehicles.inspection_due_date' ? '2027-03-21' : '2027-01-31';

    $this->actingAs($this->user)
        ->get("/budget/planned/expense/create?card=vehicle:{$vehicle->id}&deadline={$field}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('values.title', $title)
            ->where('values.card', "vehicle:{$vehicle->id}")
            ->where('values.start_date', $date)
            ->where('values.interval', $interval)
            ->where('values.interval_unit', 'year')
            ->where('values.is_estimated', true)
            ->where('values.deadline_field', $field)
            ->where('options.deadline_fields.vehicle.0.value', 'vehicles.road_tax_due_date')
            ->where('options.deadline_fields.vehicle.1.value', 'vehicles.inspection_due_date'));
})->with([
    'inspection' => ['vehicles.inspection_due_date', 'Inspection', 2],
    'road tax' => ['vehicles.road_tax_due_date', 'Road tax', 1],
]);

test('a deadline that is not of the card is ignored in the address', function (): void {
    $vehicle = insideFamilyOf($this->user, fn (): Vehicle => Vehicle::factory()->create(['family_id' => CurrentFamily::id()]));

    $this->actingAs($this->user)
        ->get("/budget/planned/expense/create?card=vehicle:{$vehicle->id}&deadline=insurance_policies.expiry_date")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('values.deadline_field', null)
            ->where('values.title', ''));
});

test('the form saves the deadline of the vehicle', function (): void {
    $vehicle = insideFamilyOf($this->user, fn (): Vehicle => Vehicle::factory()->create(['family_id' => CurrentFamily::id()]));

    $this->actingAs($this->user)->post('/budget/planned/expense', [
        'title' => 'Revisione', 'amount' => 80, 'start_date' => '2027-03-21', 'is_recurring' => true, 'interval' => 2, 'interval_unit' => 'year',
        'card' => "vehicle:{$vehicle->id}", 'deadline_field' => 'vehicles.inspection_due_date',
    ])->assertSessionHasNoErrors();

    expect(insideFamilyOf($this->user, fn (): ?string => PlannedExpense::query()->where('title', 'Revisione')->sole()->deadline_field))->toBe('vehicles.inspection_due_date');
});

test('a deadline that is not of the type of the card is refused', function (?string $card, string $field): void {
    $vehicle = insideFamilyOf($this->user, fn (): Vehicle => Vehicle::factory()->create(['family_id' => CurrentFamily::id()]));
    $card = $card === null ? null : "vehicle:{$vehicle->id}";

    $this->actingAs($this->user)->post('/budget/planned/expense', [
        'title' => 'Altro', 'amount' => 80, 'start_date' => '2027-03-21', 'interval' => 1, 'interval_unit' => 'year',
        'card' => $card, 'deadline_field' => $field,
    ])->assertSessionHasErrors('deadline_field');
})->with([
    'a policy deadline on a vehicle' => ['vehicle', 'insurance_policies.expiry_date'],
    'no card' => [null, 'vehicles.road_tax_due_date'],
    'nonsense' => ['vehicle', 'vehicles.nothing'],
]);

test('the data migration gives the deadline to the planned expenses of vehicles by title', function (): void {
    insideFamilyOf($this->user, function (): void {
        $vehicle = Vehicle::factory()->create(['family_id' => CurrentFamily::id()]);
        $policy = InsurancePolicy::factory()->create(['family_id' => CurrentFamily::id()]);
        $titles = ['Bollo auto', 'Revisione auto', 'Carburante', 'Road Tax', 'MOT inspection'];

        foreach ($titles as $title) {
            agendaPlanned($vehicle, $title, 10, '2026-01-31');
        }

        agendaPlanned($policy, 'Bollo della polizza', 10, '2026-01-31');

        (require base_path('database/migrations/2026_11_24_000004_add_deadline_field_to_planned_expenses_table.php'))->up();

        $fields = PlannedExpense::query()->pluck('deadline_field', 'title')->all();

        expect($fields['Bollo auto'])->toBe('vehicles.road_tax_due_date')
            ->and($fields['Road Tax'])->toBe('vehicles.road_tax_due_date')
            ->and($fields['Revisione auto'])->toBe('vehicles.inspection_due_date')
            ->and($fields['MOT inspection'])->toBe('vehicles.inspection_due_date')
            ->and($fields['Carburante'])->toBeNull()
            ->and($fields['Bollo della polizza'])->toBeNull();
    });
});
