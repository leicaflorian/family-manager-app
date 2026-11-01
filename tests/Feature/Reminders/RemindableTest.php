<?php

declare(strict_types=1);

use App\Enums\ReminderStatus;
use App\Models\Family;
use App\Models\Remindable;
use App\Models\Reminder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Links a reminder to a vehicle by the row of `remindables`.
 *
 * @param  Reminder  $reminder  the reminder
 * @param  Vehicle  $vehicle  the thing it refers to
 */
function linkReminder(Reminder $reminder, Vehicle $vehicle): void
{
    DB::table('remindables')->insert(['reminder_id' => $reminder->id, 'remindable_type' => 'vehicle', 'remindable_id' => $vehicle->id]);
}

test('a reminder reads the thing it refers to through its link', function (): void {
    $family = Family::factory()->create();
    $this->actingAs(User::factory()->for($family)->create());
    $vehicle = Vehicle::factory()->for($family)->create();
    $reminder = Reminder::factory()->for($family)->create();
    linkReminder($reminder, $vehicle);

    $link = Reminder::query()->findOrFail($reminder->id)->link;

    expect($link)->toBeInstanceOf(Remindable::class)
        ->and($link->reminder->is($reminder))->toBeTrue()
        ->and($link->subject)->toBeInstanceOf(Vehicle::class)
        ->and($link->subject->is($vehicle))->toBeTrue();
});

test('a reminder without a link has none', function (): void {
    $family = Family::factory()->create();
    $this->actingAs(User::factory()->for($family)->create());
    $reminder = Reminder::factory()->for($family)->create();

    expect(Reminder::query()->findOrFail($reminder->id)->link)->toBeNull();
});

test('the link of another family cannot be reached from a reminder', function (): void {
    $familyA = Family::factory()->create();
    $familyB = Family::factory()->create();
    $vehicle = Vehicle::factory()->for($familyA)->create();
    $reminder = Reminder::factory()->for($familyA)->create();
    linkReminder($reminder, $vehicle);

    $this->actingAs(User::factory()->for($familyB)->create());

    expect(Reminder::query()->find($reminder->id))->toBeNull()
        ->and(Reminder::query()->where('id', $reminder->id)->first())->toBeNull();
});

test('the open reminders', function (): void {
    $family = Family::factory()->create();
    $this->actingAs(User::factory()->for($family)->create());
    $open = Reminder::factory()->for($family)->create();
    Reminder::factory()->for($family)->create(['status' => ReminderStatus::Done]);

    expect(Reminder::query()->open()->pluck('id')->all())->toBe([$open->id])
        ->and(CurrentFamily::id())->toBe($family->id);
});
