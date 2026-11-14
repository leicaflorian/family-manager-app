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
use App\Services\Reminders\ReminderList;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

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
 * The lists of reminders as the rows the pages get (open and done), read from the list service as an account sees them.
 *
 * @return array{open: list<array<string, mixed>>, done: list<array<string, mixed>>}
 */
function remindersPage(User $user): array
{
    /** @var array{open: list<array<string, mixed>>, done: list<array<string, mixed>>} $lists */
    $lists = json_decode((string) json_encode(insideFamilyOf($user, fn (): array => resolve(ReminderList::class)->for(CarbonImmutable::today('Europe/Rome'), null))), true);

    return $lists;
}

test('a reminder is created by hand, linked to a card', function (): void {
    $vehicle = insideBianchi(fn (): Vehicle => Vehicle::query()->firstOrFail());

    $this->actingAs($this->marco)->post('/reminders', [
        'title' => 'Cambiare le gomme',
        'due_date' => '2026-11-30',
        'person_id' => null,
        'remindable' => "vehicle:{$vehicle->id}",
        'reminder_group_id' => null,
    ])->assertRedirect('/agenda');

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
    ])->assertRedirect('/agenda');

    $reminder = insideBianchi(fn (): Reminder => Reminder::query()->with('link')->findOrFail($reminder->id));

    expect($reminder->title)->toBe('Pagare il bollo della Panda')
        ->and($reminder->due_date->toDateString())->toBe('2026-12-01')
        ->and($reminder->person_id)->toBe($laura)
        ->and($reminder->reminder_group_id)->toBe($group)
        ->and($reminder->link?->remindable_id)->toBe($vehicle->id);

    $this->actingAs($this->marco)->put("/reminders/{$reminder->id}", [
        'title' => 'Pagare il bollo della Panda', 'due_date' => '2026-12-01', 'remindable' => null,
    ])->assertRedirect('/agenda');

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

    $this->actingAs($this->marco)->delete("/reminders/{$reminder->id}")->assertRedirect('/agenda');

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

    $this->post('/reminders', ['title' => 'Nuovo', 'due_date' => '2026-12-05'])->assertRedirect('/agenda');
    $this->put("/reminders/{$reminder->id}", ['title' => 'Cambiato', 'due_date' => '2026-12-06'])->assertRedirect('/agenda');
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

    $this->put("/reminders/{$reminder->id}", ['title' => 'Pagare il bollo', 'due_date' => '2026-11-20', 'remindable' => "document:{$document->id}"])->assertRedirect('/agenda');
    $this->put("/reminders/{$reminder->id}", ['title' => 'Pagare il bollo 2', 'due_date' => '2026-11-20', 'remindable' => "document:{$document->id}"])->assertRedirect('/agenda');
    $this->post('/reminders', ['title' => 'Per Laura', 'due_date' => '2026-12-01', 'remindable' => "person:{$laura->id}"])->assertRedirect('/agenda');

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

test('the form tells who gets the notices of each group, and of the default one (an account without a person by its name)', function (): void {
    $laura = insideBianchi(fn (): int => Person::query()->where('first_name', 'Laura')->sole()->id);
    insideBianchi(fn (): ReminderGroup => ReminderGroup::query()->create([
        'name' => 'Solo Laura',
        'is_default' => false,
        'deadline_types' => [],
        'notifications' => [['name' => 'Avviso', 'days' => 3, 'timing' => 'before', 'channels' => 'app', 'recipients' => [$laura]]],
    ]));

    $props = $this->actingAs($this->marco)->get('/reminders/create')->assertOk()->viewData('page')['props'];
    $groups = collect($props['groups']);

    expect($groups->firstWhere('label', 'Solo Laura')['recipients'])->toBe(['Laura'])
        ->and($groups->firstWhere('label', 'Veicoli')['recipients'])->toBe(['Marco', 'Laura', $this->member->name])
        ->and($props['default_recipients'])->toBe(['Marco', 'Laura', $this->member->name]);
});

test('the form leaves the default group out of the groups: «Default» already stands for it', function (): void {
    $props = $this->actingAs($this->marco)->get('/reminders/create')->assertOk()->viewData('page')['props'];

    expect(collect($props['groups'])->pluck('label')->all())->toBe(['Documenti e salute', 'Veicoli']);
});

test('the form says when each group notifies, as offsets from the day, and the default ones', function (): void {
    $props = $this->actingAs($this->marco)->get('/reminders/create')->assertOk()->viewData('page')['props'];
    $veicoli = collect($props['groups'])->firstWhere('label', 'Veicoli');

    expect($veicoli['offsets'])->toBe([-45, -15])
        ->and($props['default_offsets'])->toBe([-7, -1, 0]);
});

test('only an admin can delete, and not an automatic reminder', function (): void {
    $written = handWrittenReminder();
    $automatic = openAutomaticReminder();

    expect($this->actingAs($this->marco)->get("/reminders/{$written->id}/edit")->viewData('page')['props']['can_delete'])->toBeTrue()
        ->and($this->actingAs($this->member)->get("/reminders/{$written->id}/edit")->viewData('page')['props']['can_delete'])->toBeFalse()
        ->and($this->actingAs($this->marco)->get('/reminders/create')->viewData('page')['props']['can_delete'])->toBeFalse();
    $this->actingAs($this->marco)->get("/reminders/{$automatic->id}/edit")->assertForbidden();
});

test('the title of a reminder joins with its subject in one place', function (): void {
    expect(ReminderList::joinTitle('Renew the policy', 'RC auto Panda'))->toBe('Renew the policy · RC auto Panda')
        ->and(ReminderList::joinTitle('Call the plumber', null))->toBe('Call the plumber');
});

test('it sends the reminders page to the agenda', function (): void {
    $this->actingAs($this->marco)->get('/reminders')->assertRedirect('/agenda');
    $this->actingAs($this->marco)->get('/reminders?status=done&person=3')->assertRedirect('/agenda?view=done');
});

test('the reminders of a person open the agenda filtered on that person', function (): void {
    $giulia = insideBianchi(fn (): Person => Person::query()->where('first_name', 'Giulia')->sole());
    $this->marco->forceFill(['agenda_filters' => ['months' => 3, 'person_id' => null, 'hidden_areas' => ['home']]])->save();

    $this->actingAs($this->marco)->get('/reminders?person='.$giulia->id)->assertRedirect('/agenda');

    expect($this->marco->fresh()->agenda_filters)->toBe(['months' => 3, 'person_id' => $giulia->id, 'hidden_areas' => ['home']]);
});

test('a person of another family or a wrong one leaves the agenda filters as they are', function (string $person): void {
    $rossi = registerFamily('Rossi', 'mario@example.com');
    $theirs = insideFamilyOf($rossi, fn (): Person => Person::factory()->create(['family_id' => CurrentFamily::id()]));

    $this->actingAs($this->marco)->get('/reminders?person='.str_replace('{rossi}', (string) $theirs->id, $person))->assertRedirect('/agenda');

    expect($this->marco->fresh()->agenda_filters)->toBeNull();
})->with(['{rossi}', 'abc', '']);

test('it goes back to the agenda after saving or deleting a task', function (): void {
    $reminder = handWrittenReminder();
    $this->actingAs($this->marco);

    $this->post('/reminders', ['title' => 'Nuovo', 'due_date' => '2026-12-05'])->assertRedirect(route('agenda'));
    $this->put("/reminders/{$reminder->id}", ['title' => 'Cambiato', 'due_date' => '2026-12-06'])->assertRedirect(route('agenda'));
    $this->delete("/reminders/{$reminder->id}")->assertRedirect(route('agenda'));
});

test('the form no longer carries the list beside it', function (): void {
    $reminder = handWrittenReminder();
    $this->actingAs($this->marco);

    foreach (['/reminders/create', "/reminders/{$reminder->id}/edit"] as $url) {
        expect($this->get($url)->assertOk()->viewData('page')['props'])->not->toHaveKey('reminders');
    }
});

it('shows the agenda behind the task form', function (): void {
    $this->actingAs($this->marco);

    $create = $this->get('/reminders/create')->assertOk()->viewData('page')['props'];
    expect($create)->toHaveKeys(['agenda', 'done', 'done_next_page'])
        ->and($create['agenda']['entries'])->toBeArray()
        ->and($create['agenda']['view'])->toBe('todo')
        ->and($create)->not->toHaveKey('reminders');

    $reminder = handWrittenReminder();
    expect($this->get("/reminders/{$reminder->id}/edit")->assertOk()->viewData('page')['props'])->toHaveKey('agenda');
});
