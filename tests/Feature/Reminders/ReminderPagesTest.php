<?php

declare(strict_types=1);

use App\Enums\ReminderStatus;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Family;
use App\Models\Person;
use App\Models\Reminder;
use App\Models\ReminderGroup;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $this->member = User::factory()->create(['family_id' => $this->marco->family_id, 'role' => Role::Member, 'email_verified_at' => now()]);
});

afterEach(fn () => Date::setTestNow());

/**
 * A reminder written by hand in the Bianchi family.
 *
 * @param  array<string, mixed>  $state  the columns to set
 */
function handWrittenReminder(array $state = []): Reminder
{
    return insideBianchi(fn (): Reminder => Reminder::factory()->create([
        'family_id' => CurrentFamily::id(),
        'title' => 'Pagare il bollo',
        'due_date' => '2026-11-20',
        'is_automatic' => false,
        ...$state,
    ]));
}

/**
 * The first automatic reminder of the Bianchi.
 */
function openAutomaticReminder(): Reminder
{
    return insideBianchi(fn (): Reminder => Reminder::query()->where('is_automatic', true)->where('status', ReminderStatus::Open)->firstOrFail());
}

/**
 * The props of the reminders page as an account sees them.
 *
 * @return array<string, mixed>
 */
function remindersPage(User $user, string $query = ''): array
{
    return test()->actingAs($user)->get('/reminders'.$query)->assertOk()->viewData('page')['props']['reminders'];
}

test('the list is the board: the health card of Giulia is overdue with no more notices, the plumber is the first coming', function (): void {
    $page = remindersPage($this->marco);
    $open = collect($page['open']);

    $health = $open->firstWhere('title', 'Renew the document');
    $plumber = $open->firstWhere('title', "Chiamare l'idraulico per il preventivo del bagno");

    expect($health)->not->toBeNull()
        ->and($health['due_date'])->toBe('2026-10-20')
        ->and($health['next_notice'])->toBeNull()
        ->and($health['is_automatic'])->toBeTrue()
        ->and($health['person']['name'])->toBe('Giulia')
        ->and($health['subject']['label'])->toBe('Tessera sanitaria')
        ->and($health['href'])->toBe('/documents/'.$health['subject']['id'])
        ->and($open->first()['id'])->toBe($health['id'])
        ->and($open->skip(1)->first()['id'])->toBe($plumber['id'])
        ->and($plumber['next_notice'])->toBe('2026-10-30')
        ->and($plumber['notice_sent'])->toBeTrue()
        ->and($plumber['is_automatic'])->toBeFalse()
        ->and($plumber['href'])->toBe('/reminders/'.$plumber['id'].'/edit')
        ->and($plumber['subject']['type'])->toBe('home')
        ->and($plumber['subject']['href'])->toBe('/cards/home/'.$plumber['subject']['id'])
        ->and(collect($page['done'])->pluck('status')->unique()->all())->toBe(['done']);
});

test('Who filters the list', function (): void {
    $laura = insideBianchi(fn (): int => Person::query()->where('first_name', 'Laura')->sole()->id);

    $page = remindersPage($this->marco, "?person={$laura}");

    expect(collect($page['open'])->pluck('person.name')->unique()->all())->toBe(['Laura'])
        ->and($page['person_id'])->toBe($laura)
        ->and($page['done'])->toBe([]);
});

test('the list does not ask the database once for each row', function (): void {
    $this->actingAs($this->marco);
    $this->get('/reminders')->assertOk();
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $this->get('/reminders')->assertOk();
    $before = $queries;

    foreach (range(1, 6) as $i) {
        handWrittenReminder(['title' => "More {$i}"]);
    }
    $queries = 0;
    $this->get('/reminders')->assertOk();

    expect($queries)->toBeLessThanOrEqual($before);
});

test('a reminder is created by hand, linked to a card', function (): void {
    $vehicle = insideBianchi(fn (): Vehicle => Vehicle::query()->firstOrFail());

    $this->actingAs($this->marco)->post('/reminders', [
        'title' => 'Cambiare le gomme',
        'due_date' => '2026-11-30',
        'person_id' => null,
        'remindable' => "vehicle:{$vehicle->id}",
        'reminder_group_id' => null,
    ])->assertRedirect('/reminders');

    $reminder = insideBianchi(fn (): Reminder => Reminder::query()->where('title', 'Cambiare le gomme')->with('link')->sole());

    expect($reminder->is_automatic)->toBeFalse()
        ->and($reminder->status)->toBe(ReminderStatus::Open)
        ->and($reminder->due_date->toDateString())->toBe('2026-11-30')
        ->and($reminder->link?->remindable_type)->toBe('vehicle')
        ->and($reminder->link?->remindable_id)->toBe($vehicle->id);
});

test('a reminder written by hand is changed, with its group, its person and its link', function (): void {
    $reminder = handWrittenReminder();
    $laura = insideBianchi(fn (): int => Person::query()->where('first_name', 'Laura')->sole()->id);
    $group = insideBianchi(fn (): int => ReminderGroup::query()->where('name', 'Veicoli')->sole()->id);
    $vehicle = insideBianchi(fn (): Vehicle => Vehicle::query()->firstOrFail());

    $this->actingAs($this->marco)->put("/reminders/{$reminder->id}", [
        'title' => 'Pagare il bollo della Panda',
        'due_date' => '2026-12-01',
        'person_id' => $laura,
        'remindable' => "vehicle:{$vehicle->id}",
        'reminder_group_id' => $group,
    ])->assertRedirect('/reminders');

    $reminder = insideBianchi(fn (): Reminder => Reminder::query()->with('link')->findOrFail($reminder->id));

    expect($reminder->title)->toBe('Pagare il bollo della Panda')
        ->and($reminder->due_date->toDateString())->toBe('2026-12-01')
        ->and($reminder->person_id)->toBe($laura)
        ->and($reminder->reminder_group_id)->toBe($group)
        ->and($reminder->link?->remindable_id)->toBe($vehicle->id);

    $this->actingAs($this->marco)->put("/reminders/{$reminder->id}", [
        'title' => 'Pagare il bollo della Panda', 'due_date' => '2026-12-01', 'remindable' => null,
    ])->assertRedirect('/reminders');

    expect(insideBianchi(fn (): bool => Reminder::query()->findOrFail($reminder->id)->link()->exists()))->toBeFalse();
});

test('moving the date of a reminder lets its notices go out again', function (): void {
    $reminder = handWrittenReminder(['due_date' => '2026-11-20', 'notifications_sent' => ['2026-11-20:-7']]);

    $this->actingAs($this->marco)->put("/reminders/{$reminder->id}", ['title' => 'Pagare il bollo', 'due_date' => '2026-11-25'])->assertRedirect();

    $page = remindersPage($this->marco);
    $moved = collect($page['open'])->firstWhere('id', $reminder->id);

    expect($moved['notice_sent'])->toBeFalse();
});

test('a reminder written by hand is deleted by an admin', function (): void {
    $reminder = handWrittenReminder();

    $this->actingAs($this->marco)->delete("/reminders/{$reminder->id}")->assertRedirect('/reminders');

    expect(insideBianchi(fn (): bool => Reminder::query()->whereKey($reminder->id)->exists()))->toBeFalse();
});

test('the form is empty on create, filled on edit, and prefilled by ?remindable=', function (): void {
    $vehicle = insideBianchi(fn (): Vehicle => Vehicle::query()->firstOrFail());
    $this->actingAs($this->marco);

    $create = $this->get('/reminders/create')->assertOk()->viewData('page');
    expect($create['component'])->toBe('reminders/EditReminder')
        ->and($create['props']['reminder'])->toBeNull()
        ->and($create['props']['remindable'])->toBeNull();

    $prefilled = $this->get("/reminders/create?remindable=vehicle:{$vehicle->id}")->assertOk()->viewData('page')['props'];
    expect($prefilled['remindable'])->toBe("vehicle:{$vehicle->id}")
        ->and($prefilled['links'])->not->toBeEmpty()
        ->and($prefilled['people'])->not->toBeEmpty();

    $reminder = handWrittenReminder(['reminder_group_id' => null]);
    $edit = $this->get("/reminders/{$reminder->id}/edit")->assertOk()->viewData('page')['props'];
    expect($edit['reminder']['title'])->toBe('Pagare il bollo');
});

test('?remindable= of another family leaves the field empty', function (): void {
    $rossi = Family::factory()->create();
    $foreign = CurrentFamily::using($rossi->id, fn (): Vehicle => Vehicle::factory()->create(['family_id' => $rossi->id]));

    foreach (["vehicle:{$foreign->id}", 'vehicle:abc', 'nothing:1'] as $asked) {
        $props = $this->actingAs($this->marco)->get('/reminders/create?remindable='.$asked)->assertOk()->viewData('page')['props'];
        expect($props['remindable'])->toBeNull();
    }
});

test('a member creates, edits, marks done and reopens but does not delete', function (): void {
    $reminder = handWrittenReminder();
    $this->actingAs($this->member);

    $this->post('/reminders', ['title' => 'Nuovo', 'due_date' => '2026-12-05'])->assertRedirect('/reminders');
    $this->put("/reminders/{$reminder->id}", ['title' => 'Cambiato', 'due_date' => '2026-12-06'])->assertRedirect('/reminders');
    $this->post("/reminders/{$reminder->id}/done")->assertRedirect();
    expect(insideBianchi(fn (): ReminderStatus => Reminder::query()->findOrFail($reminder->id)->status))->toBe(ReminderStatus::Done);

    $this->delete("/reminders/{$reminder->id}/done")->assertRedirect();
    expect(insideBianchi(fn (): ReminderStatus => Reminder::query()->findOrFail($reminder->id)->status))->toBe(ReminderStatus::Open);

    $this->delete("/reminders/{$reminder->id}")->assertForbidden();
});

test('an automatic reminder is neither edited nor deleted', function (): void {
    $reminder = openAutomaticReminder();

    foreach ([$this->marco, $this->member] as $user) {
        $this->actingAs($user);
        $this->get("/reminders/{$reminder->id}/edit")->assertForbidden();
        $this->put("/reminders/{$reminder->id}", ['title' => 'Cambiato', 'due_date' => '2026-12-06'])->assertForbidden();
        $this->delete("/reminders/{$reminder->id}")->assertForbidden();
    }

    $this->actingAs($this->member)->post("/reminders/{$reminder->id}/done")->assertRedirect();
    expect(insideBianchi(fn (): ReminderStatus => Reminder::query()->findOrFail($reminder->id)->status))->toBe(ReminderStatus::Done);
    $this->actingAs($this->member)->delete("/reminders/{$reminder->id}/done")->assertRedirect();
    expect(insideBianchi(fn (): ReminderStatus => Reminder::query()->findOrFail($reminder->id)->status))->toBe(ReminderStatus::Open);
});

test('another family is 404, even with an invalid body', function (): void {
    $rossi = User::factory()->create(['role' => Role::Admin, 'email_verified_at' => now()]);
    $rossi->family->update(['welcomed_at' => now()]);
    $reminder = handWrittenReminder();
    $this->actingAs($rossi);

    $this->get("/reminders/{$reminder->id}/edit")->assertNotFound();
    $this->put("/reminders/{$reminder->id}", ['title' => ''])->assertNotFound();
    $this->delete("/reminders/{$reminder->id}")->assertNotFound();
    $this->post("/reminders/{$reminder->id}/done")->assertNotFound();
    $this->delete("/reminders/{$reminder->id}/done")->assertNotFound();
});

test('the body is validated, and people, groups and links of another family are refused', function (): void {
    $rossi = Family::factory()->create();
    [$person, $group, $vehicle] = CurrentFamily::using($rossi->id, fn (): array => [
        Person::factory()->create(['family_id' => $rossi->id]),
        ReminderGroup::query()->create(['name' => 'Altro', 'is_default' => false, 'deadline_types' => [], 'notifications' => []]),
        Vehicle::factory()->create(['family_id' => $rossi->id]),
    ]);
    $this->actingAs($this->marco);

    $this->post('/reminders', ['title' => '', 'due_date' => 'tomorrow'])->assertSessionHasErrors(['title', 'due_date']);
    $this->post('/reminders', ['title' => 'x', 'due_date' => '2026-12-01', 'person_id' => $person->id])->assertSessionHasErrors('person_id');
    $this->post('/reminders', ['title' => 'x', 'due_date' => '2026-12-01', 'reminder_group_id' => $group->id])->assertSessionHasErrors('reminder_group_id');
    $this->post('/reminders', ['title' => 'x', 'due_date' => '2026-12-01', 'remindable' => "vehicle:{$vehicle->id}"])->assertSessionHasErrors('remindable');
    $this->post('/reminders', ['title' => 'x', 'due_date' => '2026-12-01', 'remindable' => 'reminder:1'])->assertSessionHasErrors('remindable');
});

test('a renewal that cannot be confirmed answers with an error on the status', function (): void {
    $reminder = insideBianchi(fn (): Reminder => Reminder::factory()->create([
        'family_id' => CurrentFamily::id(), 'is_automatic' => true, 'source_field' => 'subscriptions.renewal_date', 'due_date' => '2026-10-20',
    ]));

    $this->actingAs($this->marco)->post("/reminders/{$reminder->id}/done")->assertSessionHasErrors('status');
});

test('a reminder refers to a document or a person, and keeps it when it is saved again', function (): void {
    $document = insideBianchi(fn (): Document => Document::query()->where('title', 'Tessera sanitaria')->sole());
    $laura = insideBianchi(fn (): Person => Person::query()->where('first_name', 'Laura')->sole());
    $reminder = handWrittenReminder();
    $this->actingAs($this->marco);

    $this->put("/reminders/{$reminder->id}", ['title' => 'Pagare il bollo', 'due_date' => '2026-11-20', 'remindable' => "document:{$document->id}"])->assertRedirect('/reminders');
    $this->put("/reminders/{$reminder->id}", ['title' => 'Pagare il bollo 2', 'due_date' => '2026-11-20', 'remindable' => "document:{$document->id}"])->assertRedirect('/reminders');
    $this->post('/reminders', ['title' => 'Per Laura', 'due_date' => '2026-12-01', 'remindable' => "person:{$laura->id}"])->assertRedirect('/reminders');

    $row = collect(remindersPage($this->marco)['open'])->firstWhere('title', 'Per Laura');
    $types = collect($this->get('/reminders/create')->viewData('page')['props']['links'])->pluck('type');

    expect($row['subject']['type'])->toBe('person')
        ->and($row['subject']['label'])->toBe('Laura Bianchi')
        ->and($row['subject']['href'])->toBe("/family/people/{$laura->id}")
        ->and(insideBianchi(fn (): ?string => Reminder::query()->findOrFail($reminder->id)->link?->remindable_type))->toBe('document')
        ->and($types)->toContain('document', 'person')
        ->and($types)->not->toContain('expense', 'income');
});

test('an expense is not something a reminder refers to', function (): void {
    $this->actingAs($this->marco)->post('/reminders', ['title' => 'x', 'due_date' => '2026-12-01', 'remindable' => 'expense:1'])->assertSessionHasErrors('remindable');
});

test('the title of an automatic reminder is in the language of whoever looks', function (): void {
    $page = $this->actingAs($this->marco)->withHeader('Accept-Language', 'it')->get('/reminders')->assertOk()->viewData('page')['props']['reminders'];
    $health = collect($page['open'])->firstWhere('is_automatic', true);

    expect($health['title'])->toBe('Rinnovare il documento');
});
