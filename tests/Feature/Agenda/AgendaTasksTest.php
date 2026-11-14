<?php

declare(strict_types=1);

use App\Data\AgendaEntryData;
use App\Enums\Area;
use App\Enums\ReminderStatus;
use App\Enums\Role;
use App\Models\Reminder;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AgendaTasks;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-11-11 10:00');
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
});

afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * The open tasks of the Bianchi family up to a day, as an account sees them.
 *
 * @param  User  $user  who looks
 * @param  string  $until  the last day, `yyyy-mm-dd`
 * @return list<AgendaEntryData>
 */
function openTasks(User $user, string $until = '2026-12-11'): array
{
    test()->actingAs($user);

    return CurrentFamily::using($user->family_id, fn (): array => resolve(AgendaTasks::class)->open(CarbonImmutable::parse('2026-11-11'), CarbonImmutable::parse($until)));
}

/**
 * A reminder written by hand in the family of the account.
 *
 * @param  User  $user  whose family
 * @param  array<string, mixed>  $state  its columns
 */
function taskOf(User $user, array $state): Reminder
{
    return CurrentFamily::using($user->family_id, fn (): Reminder => Reminder::factory()->create(['family_id' => $user->family_id, 'is_automatic' => false, ...$state]));
}

it('lists the open tasks of the period', function (): void {
    $inside = taskOf($this->marco, ['title' => 'Dentro', 'due_date' => '2026-11-20']);
    $outside = taskOf($this->marco, ['title' => 'Fuori', 'due_date' => '2027-03-20']);
    $done = taskOf($this->marco, ['title' => 'Fatto', 'due_date' => '2026-11-21', 'status' => ReminderStatus::Done]);
    $automatic = taskOf($this->marco, ['title' => 'Automatico', 'due_date' => '2026-11-22', 'is_automatic' => true, 'source_field' => 'documents.expiry_date']);

    $entries = collect(openTasks($this->marco));
    $found = $entries->firstWhere('key', 'task:'.$inside->id);

    expect($found->kind)->toBe('task')
        ->and($found->name)->toBe('Dentro')
        ->and($found->detail)->toBe('Task')
        ->and($found->date)->toBe('2026-11-20')
        ->and($found->reminder)->not->toBeNull()
        ->and($entries->pluck('key'))->not->toContain('task:'.$outside->id, 'task:'.$done->id, 'task:'.$automatic->id);
});

it('keeps a late task without limit', function (): void {
    $late = taskOf($this->marco, ['title' => 'In ritardo', 'due_date' => '2026-06-01']);

    expect(collect(openTasks($this->marco))->pluck('key'))->toContain('task:'.$late->id);
});

it('uses the postponed day', function (): void {
    $task = taskOf($this->marco, ['title' => 'Rimandato', 'due_date' => '2026-11-01', 'postponed_to' => '2026-11-25']);

    expect(collect(openTasks($this->marco))->firstWhere('key', 'task:'.$task->id)->date)->toBe('2026-11-25');
});

it('gives the area of the linked card', function (): void {
    $linked = taskOf($this->marco, ['title' => 'Con veicolo', 'due_date' => '2026-11-20']);
    $free = taskOf($this->marco, ['title' => 'Libero', 'due_date' => '2026-11-20']);
    CurrentFamily::using($this->marco->family_id, function () use ($linked): void {
        $vehicle = Vehicle::query()->firstOrFail();
        $linked->link()->create(['remindable_type' => $vehicle->getMorphClass(), 'remindable_id' => $vehicle->id]);
    });

    $entries = collect(openTasks($this->marco));

    expect($entries->firstWhere('key', 'task:'.$linked->id)->area)->toBe(Area::Vehicles->value)
        ->and($entries->firstWhere('key', 'task:'.$free->id)->area)->toBe('general');
});

it('lets a member edit but not delete a task', function (): void {
    $member = User::factory()->for($this->marco->family)->create(['role' => Role::Member]);
    $task = taskOf($this->marco, ['title' => 'Di tutti', 'due_date' => '2026-11-20']);

    $reminder = collect(openTasks($member))->firstWhere('key', 'task:'.$task->id)->reminder;

    expect($reminder->can_edit)->toBeTrue()
        ->and($reminder->can_delete)->toBeFalse()
        ->and($reminder->id)->toBe($task->id);
});

it('hides a task of another family', function (): void {
    $other = registerFamily('Rossi', 'rossi@example.com');
    $task = taskOf($other, ['title' => 'Dei Rossi', 'due_date' => '2026-11-20']);

    expect(collect(openTasks($this->marco))->pluck('key'))->not->toContain('task:'.$task->id)
        ->and(collect(openTasks($other))->pluck('key'))->toContain('task:'.$task->id);
});

it('names the card a task is linked to in its subtitle', function (): void {
    $linked = taskOf($this->marco, ['title' => 'Con veicolo', 'due_date' => '2026-11-20']);
    CurrentFamily::using($this->marco->family_id, function () use ($linked): void {
        $vehicle = Vehicle::query()->firstOrFail();
        $linked->link()->create(['remindable_type' => $vehicle->getMorphClass(), 'remindable_id' => $vehicle->id]);
    });

    expect(collect(openTasks($this->marco))->firstWhere('key', 'task:'.$linked->id)->subtitle)->not->toBe('');
});
