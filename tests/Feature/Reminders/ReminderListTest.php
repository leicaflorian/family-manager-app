<?php

declare(strict_types=1);

use App\Data\ReminderData;
use App\Enums\ReminderStatus;
use App\Enums\Role;
use App\Models\Person;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Reminders\ReminderList;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
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
 * The reminder lists as an account sees them, with the rows kept as objects.
 *
 * @return array{open: list<ReminderData>, done: list<ReminderData>}
 */
function listedFor(User $user, ?int $personId = null): array
{
    test()->actingAs($user);

    return insideFamilyOf($user, fn (): array => resolve(ReminderList::class)->for(CarbonImmutable::today('Europe/Rome'), $personId));
}

/**
 * A reminder of the Bianchi.
 *
 * @param  array<string, mixed>  $state  the columns to set
 */
function listReminder(User $user, array $state = []): Reminder
{
    return insideFamilyOf($user, fn (): Reminder => Reminder::factory()->create([
        'family_id' => $user->family_id, 'title' => 'Pagare il bollo', 'due_date' => '2026-11-20', 'is_automatic' => false, ...$state,
    ]));
}

test('each row says whether whoever looks may edit and delete it', function (): void {
    $written = listReminder($this->marco);
    $automatic = insideBianchi(fn (): Reminder => Reminder::query()->where('is_automatic', true)->where('status', ReminderStatus::Open)->firstOrFail());
    $row = static fn (User $user, Reminder $reminder): ReminderData => collect(listedFor($user)['open'])->firstWhere('id', $reminder->id);

    expect($row($this->marco, $written))->toMatchObject(['can_edit' => true, 'can_delete' => true])
        ->and($row($this->member, $written))->toMatchObject(['can_edit' => true, 'can_delete' => false])
        ->and($row($this->marco, $automatic))->toMatchObject(['can_edit' => false, 'can_delete' => false]);
});

test('the person filter keeps only the reminders of that person', function (): void {
    $laura = insideBianchi(fn (): int => Person::query()->where('first_name', 'Laura')->sole()->id);

    $lists = listedFor($this->marco, $laura);

    expect(collect($lists['open'])->map(static fn (ReminderData $row): ?string => $row->person?->name)->unique()->all())->toBe(['Laura'])
        ->and($lists['done'])->toBe([]);
});

test('the list does not ask the database once for each row', function (): void {
    listedFor($this->marco);
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    listedFor($this->marco);
    $before = $queries;

    foreach (range(1, 6) as $i) {
        listReminder($this->marco, ['title' => "More {$i}"]);
    }
    $queries = 0;
    listedFor($this->marco);

    expect($queries)->toBeLessThanOrEqual($before);
});

test('an automatic reminder whose thing is gone leads to the list, not to the edit page that refuses it', function (): void {
    $id = listReminder($this->marco, ['title' => 'Renew the document', 'is_automatic' => true, 'status' => ReminderStatus::Done, 'due_date' => '2026-09-01'])->id;

    $row = collect(listedFor($this->marco)['done'])->firstWhere('id', $id);

    expect($row->href)->toBe('/reminders');
});

test('a written reminder leads to its edit page and an automatic one to its thing', function (): void {
    $written = listReminder($this->marco);
    $health = collect(listedFor($this->marco)['open'])->firstWhere('title', 'Renew the document');

    expect(collect(listedFor($this->marco)['open'])->firstWhere('id', $written->id)->href)->toBe("/reminders/{$written->id}/edit")
        ->and($health->href)->toBe('/documents/'.$health->subject?->id);
});

test('the title of an automatic reminder is in the language of whoever looks', function (): void {
    App::setLocale('it');

    $health = collect(listedFor($this->marco)['open'])->firstWhere('is_automatic', true);

    expect($health->title)->toBe('Rinnovare il documento');
});
