<?php

declare(strict_types=1);

use App\Models\Owner;
use App\Models\User;
use Illuminate\Support\Facades\Date;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    Date::setTestNow('2026-11-09 10:00:00');
});

it('records the access of an account', function (): void {
    $user = User::factory()->create();

    actingAs($user)->get('/dashboard');

    expect($user->fresh()->last_seen_at?->toDateTimeString())->toBe('2026-11-09 10:00:00');
});

it('writes once in a minute', function (): void {
    $user = User::factory()->create();

    actingAs($user)->get('/dashboard');
    Date::setTestNow('2026-11-09 10:00:30');
    get('/dashboard');
    expect($user->fresh()->last_seen_at?->toDateTimeString())->toBe('2026-11-09 10:00:00');

    Date::setTestNow('2026-11-09 10:01:30');
    get('/dashboard');
    expect($user->fresh()->last_seen_at?->toDateTimeString())->toBe('2026-11-09 10:01:30');
});

it('does not write during a visit of an owner', function (): void {
    $user = User::factory()->create();

    actingAs($user)->withSession(['visit' => ['owner_id' => 1]])->get('/dashboard');

    expect($user->fresh()->last_seen_at)->toBeNull();
});

it('does not credit the panel of an owner to the account with the same id', function (): void {
    $owner = Owner::factory()->withAuthenticator()->create();
    $user = User::factory()->create(['id' => $owner->id]);

    actingAs($owner, 'owner')->get('/admin')->assertOk();

    expect($user->fresh()->last_seen_at)->toBeNull();
});
