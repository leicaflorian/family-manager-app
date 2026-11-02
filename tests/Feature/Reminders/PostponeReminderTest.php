<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Enums\ReminderStatus;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Reminder;
use App\Models\User;
use App\Support\CurrentFamily;
use App\Support\Reminders\Notices;
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
 * The automatic reminder of the health card of Giulia, overdue since 20/10/2026.
 */
function overdueHealthCardReminder(): Reminder
{
    return insideBianchi(fn (): Reminder => Reminder::query()->where('is_automatic', true)->where('status', ReminderStatus::Open)->whereDate('due_date', '2026-10-20')->sole());
}

/**
 * The open reminder of the list with this id, as the reminders page sends it.
 *
 * @param  User  $user  the account
 * @param  int  $id  the reminder
 * @return array<string, mixed>
 */
function openReminderRow(User $user, int $id): array
{
    $open = test()->actingAs($user)->get('/reminders')->assertOk()->viewData('page')['props']['reminders']['open'];

    return collect($open)->firstWhere('id', $id);
}

test('postponing moves the day of the reminder and keeps the deadline it comes from', function (): void {
    $reminder = overdueHealthCardReminder();

    $this->actingAs($this->marco)->post("/reminders/{$reminder->id}/postpone", ['to' => '2026-11-02'])->assertRedirect();

    $row = openReminderRow($this->marco, $reminder->id);
    expect($row['due_date'])->toBe('2026-11-02')
        ->and($row['postponed_from'])->toBe('2026-10-20')
        ->and($reminder->refresh()->due_date->toDateString())->toBe('2026-10-20')
        ->and($reminder->postponed_to?->toDateString())->toBe('2026-11-02');
});

test('a reminder not postponed says nothing about where it comes from', function (): void {
    $reminder = overdueHealthCardReminder();

    expect(openReminderRow($this->marco, $reminder->id)['postponed_from'])->toBeNull();
});

test('the list puts a postponed reminder at its new day', function (): void {
    $reminder = overdueHealthCardReminder();
    $this->actingAs($this->marco)->post("/reminders/{$reminder->id}/postpone", ['to' => '2026-12-31']);

    $open = $this->get('/reminders')->viewData('page')['props']['reminders']['open'];

    $days = array_column($open, 'due_date');
    $sorted = $days;
    sort($sorted);

    expect($days)->toBe($sorted)
        ->and($days[0])->not->toBe('2026-10-20')
        ->and(collect($open)->firstWhere('id', $reminder->id)['due_date'])->toBe('2026-12-31');
});

test('a postponed automatic reminder stays postponed when its card is saved again with the same date', function (): void {
    $reminder = overdueHealthCardReminder();
    $this->actingAs($this->marco)->post("/reminders/{$reminder->id}/postpone", ['to' => '2026-11-02']);

    insideBianchi(function () use ($reminder): void {
        $document = $reminder->link()->sole()->subject;
        expect($document)->toBeInstanceOf(Document::class);
        $document->forceFill(['notes' => 'Rinnovata in farmacia'])->save();
    });

    expect(Reminder::query()->withoutGlobalScopes()->find($reminder->id)?->postponed_to?->toDateString())->toBe('2026-11-02');
});

test('the notices start again from the new day', function (): void {
    $reminder = overdueHealthCardReminder();
    $this->actingAs($this->marco)->post("/reminders/{$reminder->id}/postpone", ['to' => '2026-11-10']);
    $reminder->refresh();

    $next = Notices::next($reminder, [new NotificationRule(-7, Channel::Both)], CarbonImmutable::parse('2026-10-26', 'Europe/Rome'));

    expect($next?->toDateString())->toBe('2026-11-03')
        ->and(openReminderRow($this->marco, $reminder->id)['next_notice'])->not->toBeNull();
});

test('postponing to the day of the deadline is not postponing', function (): void {
    $reminder = insideBianchi(fn (): Reminder => Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Pagare il bollo', 'due_date' => '2026-11-20', 'is_automatic' => false]));
    $this->actingAs($this->marco)->post("/reminders/{$reminder->id}/postpone", ['to' => '2026-11-27']);
    $this->post("/reminders/{$reminder->id}/postpone", ['to' => '2026-11-20']);

    expect($reminder->refresh()->postponed_to)->toBeNull();
});

test('the new day must come after today', function (string $to): void {
    $reminder = overdueHealthCardReminder();

    $this->actingAs($this->marco)->post("/reminders/{$reminder->id}/postpone", ['to' => $to])->assertSessionHasErrors('to');

    expect($reminder->refresh()->postponed_to)->toBeNull();
})->with(['2026-10-26', '2026-10-01', 'tomorrow', '']);

test('a member can postpone, as they can mark it done', function (): void {
    $reminder = overdueHealthCardReminder();

    $this->actingAs($this->member)->post("/reminders/{$reminder->id}/postpone", ['to' => '2026-11-02'])->assertRedirect();

    expect($reminder->refresh()->postponed_to?->toDateString())->toBe('2026-11-02');
});

test('a reminder of another family is not found, even with a wrong day', function (): void {
    $reminder = overdueHealthCardReminder();
    $other = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($other)->post("/reminders/{$reminder->id}/postpone", ['to' => 'never'])->assertNotFound();
});

test('a done reminder cannot be postponed', function (): void {
    $reminder = overdueHealthCardReminder();
    $reminder->forceFill(['status' => ReminderStatus::Done])->saveQuietly();

    $this->actingAs($this->marco)->post("/reminders/{$reminder->id}/postpone", ['to' => '2026-11-02'])->assertSessionHasErrors('to');
});

test('the Agenda no longer calls late the reminder of a passed deadline once postponed', function (): void {
    $reminder = overdueHealthCardReminder();
    $this->actingAs($this->marco)->post("/reminders/{$reminder->id}/postpone", ['to' => '2026-11-02']);

    $entries = $this->get('/agenda')->viewData('page')['props']['agenda']['entries'];
    $card = collect($entries)->first(static fn (array $entry): bool => $entry['kind'] === 'deadline' && $entry['date'] === '2026-10-20');

    expect($card['reminder']['state'])->toBe('open');
});
