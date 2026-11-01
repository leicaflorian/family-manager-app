<?php

declare(strict_types=1);

use App\Actions\Cards\DeleteCard;
use App\Actions\People\DeletePerson;
use App\Actions\Reminders\SyncReminders;
use App\Enums\IntervalUnit;
use App\Enums\ReminderStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\VehicleStatus;
use App\Models\Document;
use App\Models\Family;
use App\Models\HealthEvent;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\Remindable;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->family = Family::factory()->create();
    $this->admin = User::factory()->for($this->family)->admin()->create();
    $this->actingAs($this->admin);
    $this->marco = Person::factory()->for($this->family)->create(['first_name' => 'Marco']);
});

afterEach(fn () => Date::setTestNow());

/**
 * The automatic reminders of a field, with their row of `remindables`.
 *
 * @param  string  $field  the `source_field`
 * @return list<Reminder>
 */
function automatic(string $field): array
{
    return Reminder::query()->with('link')->where('is_automatic', true)->where('source_field', $field)->oldest('due_date')->get()->all();
}

test('a reminder is born when the card is saved', function (): void {
    $panda = Vehicle::factory()->for($this->family)->create(['name' => 'Panda', 'person_id' => $this->marco->id, 'inspection_due_date' => '2027-01-09']);

    $reminders = automatic('vehicles.inspection_due_date');

    expect($reminders)->toHaveCount(1)
        ->and($reminders[0]->title)->toBe('Get the inspection done')
        ->and($reminders[0]->due_date->toDateString())->toBe('2027-01-09')
        ->and($reminders[0]->status)->toBe(ReminderStatus::Open)
        ->and($reminders[0]->person_id)->toBe($this->marco->id)
        ->and($reminders[0]->link->remindable_type)->toBe('vehicle')
        ->and($reminders[0]->link->remindable_id)->toBe($panda->id);
});

test('the title is written in the language of the app', function (): void {
    config(['app.locale' => 'it']);
    Vehicle::factory()->for($this->family)->create(['inspection_due_date' => '2027-01-09']);

    expect(automatic('vehicles.inspection_due_date')[0]->title)->toBe('Fare la revisione');
});

test('it does not repeat itself', function (): void {
    $panda = Vehicle::factory()->for($this->family)->create(['inspection_due_date' => '2027-01-09']);

    $panda->update(['name' => 'Panda 2']);
    resolve(SyncReminders::class)->handle($panda, CarbonImmutable::today('Europe/Rome'));

    expect(automatic('vehicles.inspection_due_date'))->toHaveCount(1);
});

test('a moved date replaces the open reminder', function (): void {
    $panda = Vehicle::factory()->for($this->family)->create(['road_tax_due_date' => '2027-03-31']);

    $panda->update(['road_tax_due_date' => '2028-03-31']);

    $reminders = automatic('vehicles.road_tax_due_date');
    expect($reminders)->toHaveCount(1)
        ->and($reminders[0]->due_date->toDateString())->toBe('2028-03-31')
        ->and(Remindable::query()->count())->toBe(1);
});

test('a done reminder is never touched', function (): void {
    $panda = Vehicle::factory()->for($this->family)->create(['road_tax_due_date' => '2027-03-31']);
    $done = automatic('vehicles.road_tax_due_date')[0];
    $done->update(['status' => ReminderStatus::Done]);

    $panda->update(['road_tax_due_date' => '2028-03-31']);

    $reminders = automatic('vehicles.road_tax_due_date');
    expect($reminders)->toHaveCount(2)
        ->and($reminders[0]->id)->toBe($done->id)
        ->and($reminders[0]->status)->toBe(ReminderStatus::Done)
        ->and($reminders[0]->link)->not->toBeNull()
        ->and($reminders[1]->due_date->toDateString())->toBe('2028-03-31')
        ->and($reminders[1]->status)->toBe(ReminderStatus::Open);
});

test('the person follows the card', function (): void {
    $anna = Person::factory()->for($this->family)->create(['first_name' => 'Anna']);
    $panda = Vehicle::factory()->for($this->family)->create(['person_id' => $this->marco->id, 'inspection_due_date' => '2027-01-09']);

    $panda->update(['person_id' => $anna->id]);

    expect(automatic('vehicles.inspection_due_date')[0]->person_id)->toBe($anna->id);

    $panda->update(['person_id' => null]);

    expect(automatic('vehicles.inspection_due_date')[0]->person_id)->toBeNull();
});

test('a sold vehicle loses its open reminders', function (): void {
    $panda = Vehicle::factory()->for($this->family)->create(['road_tax_due_date' => '2027-03-31', 'inspection_due_date' => '2027-01-09']);
    automatic('vehicles.road_tax_due_date')[0]->update(['status' => ReminderStatus::Done]);

    $panda->update(['status' => VehicleStatus::Sold]);

    expect(automatic('vehicles.inspection_due_date'))->toBeEmpty()
        ->and(automatic('vehicles.road_tax_due_date'))->toHaveCount(1)
        ->and(Remindable::query()->count())->toBe(1);
});

test('an open reminder already late stays while the card has the date', function (): void {
    $panda = Vehicle::factory()->for($this->family)->create(['inspection_due_date' => '2026-11-10']);

    Date::setTestNow(CarbonImmutable::parse('2026-12-01 10:00', 'Europe/Rome'));
    $panda->update(['name' => 'Panda']);

    expect(automatic('vehicles.inspection_due_date'))->toHaveCount(1);
});

test('a deleted card loses its open reminders and its rows', function (): void {
    $panda = Vehicle::factory()->for($this->family)->create(['road_tax_due_date' => '2027-03-31', 'inspection_due_date' => '2027-01-09']);
    automatic('vehicles.road_tax_due_date')[0]->update(['status' => ReminderStatus::Done]);

    resolve(DeleteCard::class)->handle($panda);

    expect(automatic('vehicles.inspection_due_date'))->toBeEmpty()
        ->and(Remindable::query()->count())->toBe(0)
        ->and(Reminder::query()->count())->toBe(1);
});

test('a planned expense syncs the card it leaves and the one it reaches', function (): void {
    $netflix = Subscription::factory()->for($this->family)->create(['renewal_date' => '2027-02-01', 'status' => SubscriptionStatus::Active]);
    $spotify = Subscription::factory()->for($this->family)->create(['renewal_date' => '2027-03-01', 'status' => SubscriptionStatus::Active]);

    expect(automatic('subscriptions.renewal_date'))->toBeEmpty();

    $fee = PlannedExpense::factory()->forCard($netflix)->create(['family_id' => $this->family->id, 'interval_unit' => IntervalUnit::Year]);

    expect(automatic('subscriptions.renewal_date'))->toHaveCount(1)
        ->and(automatic('subscriptions.renewal_date')[0]->link->remindable_id)->toBe($netflix->id);

    $fee->update(['card_id' => $spotify->id]);

    $reminders = automatic('subscriptions.renewal_date');
    expect($reminders)->toHaveCount(1)
        ->and($reminders[0]->link->remindable_id)->toBe($spotify->id);

    $fee->delete();

    expect(automatic('subscriptions.renewal_date'))->toBeEmpty();
});

test('a manual reminder is never touched', function (): void {
    $panda = Vehicle::factory()->for($this->family)->create(['inspection_due_date' => '2027-01-09']);
    $manual = Reminder::factory()->for($this->family)->create(['title' => 'Call the garage', 'due_date' => '2026-11-03']);
    DB::table('remindables')->insert(['reminder_id' => $manual->id, 'remindable_type' => 'vehicle', 'remindable_id' => $panda->id]);

    $panda->update(['status' => VehicleStatus::Sold]);
    resolve(DeleteCard::class)->handle($panda);

    expect(Reminder::query()->findOrFail($manual->id)->title)->toBe('Call the garage');
});

test('deleting a document, a person or an organization keeps the reminder without its row', function (): void {
    $document = Document::factory()->for($this->family)->create(['expiry_date' => '2027-05-05']);
    $organization = Organization::factory()->for($this->family)->create();
    $person = Person::factory()->for($this->family)->create();
    $event = HealthEvent::factory()->for($this->family)->create(['person_id' => $person->id, 'next_checkup_date' => '2027-06-06']);

    $manual = [];
    foreach ([['document', $document], ['organization', $organization], ['person', $person]] as [$type, $subject]) {
        $reminder = Reminder::factory()->for($this->family)->create();
        DB::table('remindables')->insert(['reminder_id' => $reminder->id, 'remindable_type' => $type, 'remindable_id' => $subject->id]);
        $manual[$type] = $reminder;
    }

    expect(automatic('documents.expiry_date'))->toHaveCount(1)
        ->and(automatic('health_events.next_checkup_date'))->toHaveCount(1);

    $this->delete(route('documents.destroy', $document))->assertRedirect();
    $this->delete(route('organizations.destroy', $organization))->assertRedirect();
    resolve(DeletePerson::class)->handle($person);

    foreach ($manual as $reminder) {
        expect(Reminder::query()->find($reminder->id))->not->toBeNull()
            ->and(Remindable::query()->where('reminder_id', $reminder->id)->exists())->toBeFalse();
    }

    expect(automatic('documents.expiry_date'))->toBeEmpty()
        ->and(automatic('health_events.next_checkup_date'))->toBeEmpty()
        ->and(Remindable::query()->count())->toBe(0)
        ->and(HealthEvent::query()->find($event->id))->toBeNull();
});

test('the round of a family gives the cards their reminders', function (): void {
    $panda = Vehicle::factory()->for($this->family)->create(['inspection_due_date' => '2027-01-09']);
    Reminder::query()->delete();
    DB::table('remindables')->delete();

    resolve(SyncReminders::class)->family(CarbonImmutable::today('Europe/Rome'));

    expect(automatic('vehicles.inspection_due_date'))->toHaveCount(1)
        ->and(automatic('vehicles.inspection_due_date')[0]->link->remindable_id)->toBe($panda->id);
});
