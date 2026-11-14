<?php

declare(strict_types=1);

use App\Enums\ReminderStatus;
use App\Models\Document;
use App\Models\Person;
use App\Models\Reminder;
use App\Models\User;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-11-11 10:00');
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    // The seeder has done reminders of its own; the cases below count their own
    Reminder::query()->withoutGlobalScopes()->where('status', ReminderStatus::Done)->delete();
});

afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * A done reminder of the family of Marco, marked done on a day by an account.
 *
 * @param  string  $doneOn  the day it was marked done, `yyyy-mm-dd`
 * @param  array<string, mixed>  $state  its columns
 */
function doneReminder(string $doneOn, array $state = []): Reminder
{
    $reminder = CurrentFamily::using(test()->marco->family_id, fn (): Reminder => Reminder::factory()->create([
        'family_id' => test()->marco->family_id,
        'is_automatic' => false,
        'status' => ReminderStatus::Done,
        ...$state,
    ]));

    // The day it was marked done is `updated_at`; a query keeps Eloquent from touching it
    DB::table('reminders')->where('id', $reminder->id)->update(['updated_at' => $doneOn.' 12:00:00', 'updated_by' => test()->marco->id]);

    return $reminder;
}

/**
 * The props of the Agenda as Marco sees it.
 *
 * @param  string  $query  the query string, for example `?view=done`
 * @return array<string, mixed>
 */
function doneAgenda(string $query = '?view=done'): array
{
    return test()->actingAs(test()->marco)->get('/agenda'.$query)->assertOk()->viewData('page')['props']['agenda'];
}

it('lists the done things, latest first', function (): void {
    $old = doneReminder('2026-11-01', ['title' => 'Vecchio', 'due_date' => '2026-10-20']);
    $recent = doneReminder('2026-11-09', ['title' => 'Recente', 'due_date' => '2026-10-25']);

    $agenda = doneAgenda();

    expect($agenda['view'])->toBe('done')
        ->and(collect($agenda['done'])->pluck('key')->take(2)->all())->toBe(['task:'.$recent->id, 'task:'.$old->id])
        ->and($agenda['done'][0]['reminder']['done_on'])->toBe('2026-11-09')
        ->and($agenda['done'][0]['reminder']['done_by'])->toBe($this->marco->name)
        ->and($agenda['done'][0]['kind'])->toBe('task')
        ->and($agenda['done'][0]['reminder']['state'])->toBe('done');
});

it('shows a done automatic reminder as a deadline of its document', function (): void {
    $document = CurrentFamily::using($this->marco->family_id, fn (): Document => Document::query()->firstOrFail());
    $reminder = doneReminder('2026-11-10', ['title' => 'x', 'is_automatic' => true, 'source_field' => 'documents.expiry_date', 'due_date' => '2026-10-30']);
    $reminder->link()->create(['remindable_type' => $document->getMorphClass(), 'remindable_id' => $document->id]);

    $entry = collect(doneAgenda()['done'])->firstWhere('reminder.id', $reminder->id);

    expect($entry['kind'])->toBe('deadline')
        ->and($entry['href'])->toBe(route('documents.show', $document, absolute: false))
        ->and($entry['reminder_done'])->toBeTrue()
        ->and($entry['date'])->toBe('2026-10-30');
});

it('pages the done things by 20', function (): void {
    foreach (range(1, 25) as $n) {
        doneReminder('2026-10-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT), ['title' => 'Fatto '.$n, 'due_date' => '2026-10-01']);
    }

    $first = doneAgenda();
    $second = doneAgenda('?view=done&done_page=2');

    expect($first['done'])->toHaveCount(20)
        ->and($first['done_next_page'])->toBe(2)
        ->and($second['done'])->toHaveCount(5)
        ->and($second['done_next_page'])->toBeNull();
});

it('filters the done things by person', function (): void {
    $giulia = CurrentFamily::using($this->marco->family_id, fn (): Person => Person::query()->where('first_name', 'Giulia')->firstOrFail());
    $hers = doneReminder('2026-11-09', ['title' => 'Di Giulia', 'person_id' => $giulia->id]);
    $other = doneReminder('2026-11-08', ['title' => 'Di nessuno', 'person_id' => null]);
    $this->marco->forceFill(['agenda_filters' => ['months' => 1, 'person_id' => $giulia->id, 'hidden_areas' => []]])->save();

    $keys = collect(doneAgenda()['done'])->pluck('key');

    expect($keys)->toContain('task:'.$hers->id)->not->toContain('task:'.$other->id);
});

it('has no paid payments among the done things', function (): void {
    doneReminder('2026-11-09', ['title' => 'Fatto']);

    expect(collect(doneAgenda()['done'])->pluck('kind')->unique()->all())->each->toBeIn(['task', 'deadline']);
});

it('sends no done things in the to do view', function (): void {
    doneReminder('2026-11-09', ['title' => 'Fatto']);

    $agenda = doneAgenda('');

    expect($agenda['view'])->toBe('todo')
        ->and($agenda['done'])->toBe([])
        ->and($agenda['done_next_page'])->toBeNull();
});
