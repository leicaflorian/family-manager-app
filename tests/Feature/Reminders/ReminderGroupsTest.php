<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Family;
use App\Models\Person;
use App\Models\Reminder;
use App\Models\ReminderGroup;
use App\Models\User;
use App\Support\CurrentFamily;
use App\Support\Reminders\NotificationRule;
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
 * The groups of the Bianchi as the page sends them back, ready to be changed.
 *
 * @return list<array<string, mixed>>
 */
function sentGroups(): array
{
    return insideBianchi(fn (): array => ReminderGroup::query()->orderBy('id')->get()->map(static fn (ReminderGroup $group): array => [
        'id' => $group->id,
        'name' => $group->name,
        'is_default' => $group->is_default,
        'deadline_types' => $group->deadline_types,
        'notifications' => $group->notifications,
    ])->all());
}

/**
 * The first person of the Bianchi without an account.
 */
function personWithoutAccount(): Person
{
    return insideBianchi(fn (): Person => Person::query()->whereNull('user_id')->firstOrFail());
}

/**
 * The id of the person of Marco.
 */
function marcoPersonId(): int
{
    return insideBianchi(fn (): int => Person::query()->where('user_id', test()->marco->id)->sole()->id);
}

/**
 * A saved group of the Bianchi, read again.
 */
function savedGroup(string $name): ReminderGroup
{
    return insideBianchi(fn (): ReminderGroup => ReminderGroup::query()->where('name', $name)->sole());
}

test('the page shows the groups with the kinds in words, the rules, the example and who has no account', function (): void {
    $props = test()->actingAs($this->marco)->get('/settings/notices')->assertOk()->viewData('page')['props']['settings'];

    expect($props['can_change'])->toBeTrue()
        ->and(collect($props['groups'])->pluck('name')->all())->toBe(['Veicoli', 'Documenti e salute', 'Predefinito'])
        ->and($props['without_account'])->toBe(['Giulia'])
        ->and(collect($props['people'])->pluck('name')->all())->toContain('Marco', 'Laura')
        ->and(collect($props['kinds'])->pluck('value')->all())->toContain('vehicles.road_tax_due_date');

    $cars = collect($props['groups'])->firstWhere('name', 'Veicoli');
    expect($cars['rules'][0])->toMatchArray(['days' => 45, 'timing' => 'before', 'channels' => 'both', 'recipients' => ['all']])
        ->and($cars['deadline_types'])->toBe(['vehicles.road_tax_due_date', 'vehicles.inspection_due_date']);
});

test('the example uses the next card of the group that expires, none without cards', function (): void {
    $groups = test()->actingAs($this->marco)->get('/settings/notices')->viewData('page')['props']['settings']['groups'];

    expect(collect($groups)->firstWhere('name', 'Veicoli')['example'])->toBe('With inspection – Fiat Panda – of 09/01/2027 the notices go out on 25/11 and 25/12.')
        ->and(collect($groups)->firstWhere('name', 'Predefinito')['example'])->toBeNull();

    insideBianchi(function (): void {
        Reminder::query()->where('is_automatic', true)->delete();
    });
    $props = test()->actingAs($this->marco)->get('/settings/notices')->viewData('page')['props']['settings'];

    expect(collect($props['groups'])->firstWhere('name', 'Veicoli')['example'])->toBeNull();
});

test('an administrator saves the groups', function (): void {
    $groups = sentGroups();
    $groups[0]['name'] = 'Cars';
    $groups[0]['notifications'] = [['name' => 'Promemoria', 'days' => 15, 'timing' => 'before', 'channels' => 'app', 'recipients' => [marcoPersonId()]]];

    test()->actingAs($this->marco)->put('/settings/notices', ['groups' => $groups])->assertRedirect('/settings/notices');

    $saved = savedGroup('Cars');
    expect($saved->notifications)->toEqual([['name' => 'Promemoria', 'days' => 15, 'timing' => 'before', 'channels' => 'app', 'recipients' => [marcoPersonId()]]])
        ->and(NotificationRule::listOf($saved->notifications)[0]->people)->toBe([marcoPersonId()]);
});

test('a kind moves from one group to the other', function (): void {
    $groups = sentGroups();
    $groups[0]['deadline_types'] = ['vehicles.road_tax_due_date'];
    $groups[1]['deadline_types'] = ['vehicles.inspection_due_date', 'documents.expiry_date', 'health_events.next_checkup_date'];

    test()->actingAs($this->marco)->put('/settings/notices', ['groups' => $groups])->assertSessionHasNoErrors();

    expect(savedGroup('Veicoli')->deadline_types)->toBe(['vehicles.road_tax_due_date'])
        ->and(savedGroup('Documenti e salute')->deadline_types)->toContain('vehicles.inspection_due_date');
});

test('a kind in two groups is refused', function (): void {
    $groups = sentGroups();
    $groups[1]['deadline_types'] = ['vehicles.inspection_due_date'];

    test()->actingAs($this->marco)->put('/settings/notices', ['groups' => $groups])->assertSessionHasErrors('groups');

    expect(savedGroup('Veicoli')->deadline_types)->toContain('vehicles.inspection_due_date');
});

test('another group becomes the default', function (): void {
    $groups = sentGroups();
    $groups[0]['is_default'] = true;
    $groups[2]['is_default'] = false;

    test()->actingAs($this->marco)->put('/settings/notices', ['groups' => $groups])->assertSessionHasNoErrors();

    expect(savedGroup('Veicoli')->is_default)->toBeTrue()
        ->and(savedGroup('Predefinito')->is_default)->toBeFalse();
});

test('two default groups, or none, are refused', function (): void {
    $two = sentGroups();
    $two[0]['is_default'] = true;
    $none = sentGroups();
    $none[2]['is_default'] = false;

    test()->actingAs($this->marco)->put('/settings/notices', ['groups' => $two])->assertSessionHasErrors('groups');
    test()->actingAs($this->marco)->put('/settings/notices', ['groups' => $none])->assertSessionHasErrors('groups');
});

test('a group left out is deleted, never the default one', function (): void {
    $groups = sentGroups();
    $withoutVehicles = array_slice($groups, 1);
    $withoutDefault = array_slice($groups, 0, 2);

    test()->actingAs($this->marco)->put('/settings/notices', ['groups' => $withoutDefault])->assertSessionHasErrors('groups');
    expect(insideBianchi(fn (): int => ReminderGroup::query()->count()))->toBe(3);

    $reminder = insideBianchi(fn (): Reminder => Reminder::factory()->create([
        'family_id' => CurrentFamily::id(),
        'is_automatic' => false,
        'reminder_group_id' => $groups[0]['id'],
    ]));

    test()->actingAs($this->marco)->put('/settings/notices', ['groups' => $withoutVehicles])->assertSessionHasNoErrors();
    expect(insideBianchi(fn (): int => ReminderGroup::query()->count()))->toBe(2)
        ->and(insideBianchi(fn (): ?int => Reminder::query()->findOrFail($reminder->id)->reminder_group_id))->toBeNull();
});

test('a new group is created', function (): void {
    $groups = [...sentGroups(), ['id' => null, 'name' => 'Home', 'is_default' => false, 'deadline_types' => ['belongings.warranty_expiry_date', 'ghost.field'], 'notifications' => []]];

    test()->actingAs($this->marco)->put('/settings/notices', ['groups' => $groups])->assertSessionHasNoErrors();

    expect(savedGroup('Home')->deadline_types)->toBe(['belongings.warranty_expiry_date'])
        ->and(savedGroup('Home')->family_id)->toBe($this->marco->family_id);
});

test('a person of another family or without an account is refused', function (): void {
    $stranger = Person::factory()->for(Family::factory())->create();
    $row = ['name' => 'Promemoria', 'days' => 3, 'timing' => 'before', 'channels' => 'app'];

    foreach ([$stranger->id, personWithoutAccount()->id, 999999] as $id) {
        $groups = sentGroups();
        $groups[0]['notifications'] = [[...$row, 'recipients' => [$id]]];

        test()->actingAs($this->marco)->put('/settings/notices', ['groups' => $groups])->assertSessionHasErrors('groups');
    }

    expect(savedGroup('Veicoli')->notifications[0]['recipients'])->toBe(['all']);
});

test('a group of another family is refused', function (): void {
    $other = ReminderGroup::factory()->create(['is_default' => true]);
    $groups = [...sentGroups(), ['id' => $other->id, 'name' => 'Hijacked', 'is_default' => false, 'deadline_types' => [], 'notifications' => []]];

    test()->actingAs($this->marco)->put('/settings/notices', ['groups' => $groups])->assertSessionHasErrors('groups.3.id');

    expect($other->fresh()->name)->not->toBe('Hijacked');
});

test('a group copied from Directus with recipients as text reads, and keeps its name when saved back', function (): void {
    $copied = insideBianchi(fn (): ReminderGroup => ReminderGroup::query()->where('name', 'Veicoli')->sole());
    $copied->update(['notifications' => [['name' => 'Bollo', 'days' => '45', 'timing' => 'before', 'channels' => 'both', 'recipients' => [(string) marcoPersonId()]]]]);

    $props = test()->actingAs($this->marco)->get('/settings/notices')->viewData('page')['props']['settings'];
    $rule = collect($props['groups'])->firstWhere('name', 'Veicoli')['rules'][0];

    expect($rule)->toMatchArray(['name' => 'Bollo', 'days' => 45, 'recipients' => [marcoPersonId()]]);
});

test('a member sees the page without the right to change and gets 403 on PUT', function (): void {
    $props = test()->actingAs($this->member)->get('/settings/notices')->assertOk()->viewData('page')['props']['settings'];

    expect($props['can_change'])->toBeFalse();

    test()->actingAs($this->member)->put('/settings/notices', ['groups' => sentGroups()])->assertForbidden();
});

test('a guest is sent to sign in', function (): void {
    test()->get('/settings/notices')->assertRedirect('/login');
    test()->put('/settings/notices', [])->assertRedirect('/login');
});

test('the same group sent twice is refused', function (): void {
    $groups = sentGroups();
    $groups[] = [...$groups[0], 'name' => 'Copy', 'deadline_types' => []];

    test()->actingAs($this->marco)->put('/settings/notices', ['groups' => $groups])->assertSessionHasErrors('groups');

    expect(savedGroup('Veicoli')->name)->toBe('Veicoli');
});
