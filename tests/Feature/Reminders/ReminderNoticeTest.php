<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\Reminder;
use App\Models\ReminderNotice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('a notice is only of its account', function (): void {
    $family = Family::factory()->create();
    $owner = User::factory()->for($family)->create(['role' => 'admin']);
    $other = User::factory()->for($family)->create(['role' => 'admin']);
    $this->actingAs($owner);
    $notice = ReminderNotice::factory()->for($family)->for($owner)->create();

    expect(Gate::forUser($owner)->allows('view', $notice))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', $notice))->toBeTrue()
        ->and(Gate::forUser($other)->allows('view', $notice))->toBeFalse()
        ->and(Gate::forUser($other)->allows('update', $notice))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('create', ReminderNotice::class))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('delete', $notice))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('deleteAny', ReminderNotice::class))->toBeFalse();
});

test('a notice knows its reminder and its account, and can be filtered unread', function (): void {
    $family = Family::factory()->create();
    $user = User::factory()->for($family)->create();
    $this->actingAs($user);
    $reminder = Reminder::factory()->for($family)->create();
    $unread = ReminderNotice::factory()->for($family)->for($user)->for($reminder)->create();
    ReminderNotice::factory()->for($family)->for($user)->for($reminder)->create(['read_at' => now()]);

    expect(ReminderNotice::query()->unread()->pluck('id')->all())->toBe([$unread->id])
        ->and($unread->reminder->is($reminder))->toBeTrue()
        ->and($unread->user->is($user))->toBeTrue();
});

test('prune keeps 90 days', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    $family = Family::factory()->create();
    $user = User::factory()->for($family)->create();
    $old = ReminderNotice::factory()->for($family)->for($user)->create(['sent_at' => now()->subDays(91)]);
    $edge = ReminderNotice::factory()->for($family)->for($user)->create(['sent_at' => now()->subDays(89)]);

    (new ReminderNotice)->pruneAll();

    expect(ReminderNotice::query()->withoutGlobalScopes()->pluck('id')->all())->toBe([$edge->id])
        ->and($old->exists)->toBeTrue();
});
