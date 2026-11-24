<?php

declare(strict_types=1);

use App\Actions\Budget\ChangeOccurrence;
use App\Actions\Budget\RecordPayment;
use App\Enums\IntervalUnit;
use App\Enums\ReminderStatus;
use App\Enums\VehicleStatus;
use App\Models\PlannedExpense;
use App\Models\Reminder;
use App\Models\Vehicle;
use App\Services\Deadlines;
use App\Support\CurrentFamily;
use App\Support\Deadline;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2027-04-10 10:00', 'Europe/Rome'));
    $this->user = registerFamily();
});

afterEach(fn () => Date::setTestNow());

/**
 * A vehicle with its inspection planned every 2 years from the given start.
 *
 * @param  string  $start  the start of the planned expense
 * @param  array<string, mixed>  $state  the columns of the planned expense that change
 * @return array{Vehicle, PlannedExpense}
 */
function followVehicle(string $start, string $inspection, array $state = []): array
{
    $vehicle = Vehicle::factory()->create(['family_id' => CurrentFamily::id(), 'status' => VehicleStatus::Active, 'inspection_due_date' => $inspection]);
    $planned = agendaPlanned($vehicle, 'Revisione', 80, $start, ['deadline_field' => 'vehicles.inspection_due_date', 'is_recurring' => true, 'interval' => 2, 'interval_unit' => IntervalUnit::Year, ...$state]);

    return [$vehicle, $planned];
}

/**
 * The real dates of the due dates of a planned expense in a range.
 *
 * @return list<string>
 */
function followDates(PlannedExpense $planned, string $from, string $to): array
{
    return array_map(
        static fn (Deadline $deadline): string => $deadline->occurrence->date->toDateString(),
        resolve(Deadlines::class)->between([$planned->fresh()], CarbonImmutable::parse($from), CarbonImmutable::parse($to)),
    );
}

test('the next unpaid due date follows the new date of the card and the paid one stays', function (): void {
    insideFamilyOf($this->user, function (): void {
        [$vehicle, $planned] = followVehicle('2027-03-21', '2027-03-21');
        $due = resolve(Deadlines::class)->find($planned, CarbonImmutable::parse('2027-03-21'));
        resolve(RecordPayment::class)->handle($due, CarbonImmutable::parse('2027-03-21'), 80.0, null, null);

        $vehicle->update(['inspection_due_date' => '2029-03-10']);

        expect(followDates($planned, '2027-01-01', '2029-12-31'))->toBe(['2027-03-21', '2029-03-10'])
            ->and($planned->expenses()->count())->toBe(1)
            ->and($planned->expenses()->sole()->occurrence_date->toDateString())->toBe('2027-03-21');
    });
});

test('a planned expense without payments starts earlier when the card date comes before its start', function (): void {
    insideFamilyOf($this->user, function (): void {
        [$vehicle, $planned] = followVehicle('2027-03-21', '2027-03-21');

        $vehicle->update(['inspection_due_date' => '2027-03-15']);

        expect($planned->fresh()->start_date->toDateString())->toBe('2027-03-15')
            ->and($planned->exceptions()->count())->toBe(0);
    });
});

test('a cleared date changes nothing', function (): void {
    insideFamilyOf($this->user, function (): void {
        [$vehicle, $planned] = followVehicle('2027-03-21', '2027-03-21');

        $vehicle->update(['inspection_due_date' => null]);

        expect($planned->fresh()->start_date->toDateString())->toBe('2027-03-21')
            ->and($planned->exceptions()->count())->toBe(0);
    });
});

test('another change of the card does not touch the planned expense', function (): void {
    insideFamilyOf($this->user, function (): void {
        [$vehicle, $planned] = followVehicle('2027-03-21', '2027-03-21');

        $vehicle->update(['name' => 'The other car']);

        expect($planned->fresh()->start_date->toDateString())->toBe('2027-03-21')
            ->and($planned->exceptions()->count())->toBe(0);
    });
});

test('a planned expense without a deadline field does not move', function (): void {
    insideFamilyOf($this->user, function (): void {
        [$vehicle, $planned] = followVehicle('2027-03-21', '2027-03-21', ['deadline_field' => null]);

        $vehicle->update(['inspection_due_date' => '2027-03-15']);

        expect($planned->fresh()->start_date->toDateString())->toBe('2027-03-21')
            ->and($planned->exceptions()->count())->toBe(0);
    });
});

test('the planned expense of the other deadline of the vehicle does not move', function (): void {
    insideFamilyOf($this->user, function (): void {
        [$vehicle, $planned] = followVehicle('2027-03-21', '2027-03-21', ['deadline_field' => 'vehicles.road_tax_due_date']);

        $vehicle->update(['inspection_due_date' => '2027-03-15']);

        expect($planned->fresh()->start_date->toDateString())->toBe('2027-03-21');
    });
});

test('a date that already matches the due date adds no exception', function (): void {
    insideFamilyOf($this->user, function (): void {
        [$vehicle, $planned] = followVehicle('2027-03-21', '2027-01-01');

        $vehicle->update(['inspection_due_date' => '2027-03-21']);

        expect($planned->exceptions()->count())->toBe(0);
    });
});

test('a one-off planned expense follows the card date whatever the distance', function (): void {
    insideFamilyOf($this->user, function (): void {
        [$vehicle, $planned] = followVehicle('2027-03-21', '2027-03-21', ['is_recurring' => false]);

        $vehicle->update(['inspection_due_date' => '2028-03-21']);

        expect($planned->fresh()->start_date->toDateString())->toBe('2028-03-21')
            ->and($planned->exceptions()->count())->toBe(0);
    });
});

test('a due date moved twice keeps only the second move', function (): void {
    insideFamilyOf($this->user, function (): void {
        [$vehicle, $planned] = followVehicle('2027-03-21', '2027-03-21');

        $vehicle->update(['inspection_due_date' => '2027-03-25']);
        $vehicle->update(['inspection_due_date' => '2027-03-30']);

        expect(followDates($planned, '2027-01-01', '2027-12-31'))->toBe(['2027-03-30'])
            ->and($planned->exceptions()->count())->toBe(1);
    });
});

test('the amount chosen for a due date stays when the date moves', function (): void {
    insideFamilyOf($this->user, function (): void {
        [$vehicle, $planned] = followVehicle('2027-03-21', '2027-03-21');
        resolve(ChangeOccurrence::class)->handle($planned, CarbonImmutable::parse('2027-03-21'), false, 95.0, null, null);

        $vehicle->update(['inspection_due_date' => '2027-03-25']);

        $due = resolve(Deadlines::class)->between([$planned->fresh()], CarbonImmutable::parse('2027-01-01'), CarbonImmutable::parse('2027-12-31'))[0];
        expect($due->occurrence->date->toDateString())->toBe('2027-03-25')
            ->and($due->occurrence->amount)->toBe(95.0);
    });
});

test('paying a moved due date closes the reminder of the moved date', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2027-02-01 10:00', 'Europe/Rome'));
    insideFamilyOf($this->user, function (): void {
        [$vehicle, $planned] = followVehicle('2027-03-21', '2027-03-21');
        $vehicle->update(['inspection_due_date' => '2027-03-25']);
        $reminder = Reminder::query()->where('source_field', 'vehicles.inspection_due_date')->sole();
        $due = resolve(Deadlines::class)->find($planned->fresh(), CarbonImmutable::parse('2027-03-21'));
        resolve(RecordPayment::class)->handle($due, CarbonImmutable::parse('2027-03-25'), 80.0, null, null);

        expect($reminder->fresh()->due_date->toDateString())->toBe('2027-03-25')
            ->and($reminder->fresh()->status)->toBe(ReminderStatus::Done);
    });
});

test('a failure while following does not break the save nor the reminders', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2027-02-01 10:00', 'Europe/Rome'));
    Exceptions::fake();

    insideFamilyOf($this->user, function (): void {
        [$vehicle, $planned] = followVehicle('2027-03-21', '2027-03-21');
        // The planned expense cannot be saved: the follow fails for real
        Event::listen('eloquent.updating: '.PlannedExpense::class, static fn () => throw new RuntimeException('boom'));
        $vehicle->update(['inspection_due_date' => '2027-03-15']);
        Event::forget('eloquent.updating: '.PlannedExpense::class);

        expect($vehicle->fresh()->inspection_due_date->toDateString())->toBe('2027-03-15')
            ->and($planned->fresh()->start_date->toDateString())->toBe('2027-03-21')
            ->and(Reminder::query()->where('source_field', 'vehicles.inspection_due_date')->where('due_date', '2027-03-15')->exists())->toBeTrue();
    });
    Exceptions::assertReported(RuntimeException::class);
});
