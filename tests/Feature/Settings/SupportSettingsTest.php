<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
    Date::setTestNow(Date::parse('2026-12-09 21:30', 'Europe/Rome'));
});

afterEach(function (): void {
    Date::setTestNow();
});

test('the page says support cannot come in when the permission is off', function (): void {
    $this->get(route('settings.support.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('settings/Support')
        ->where('support_until', null)
        ->where('can_change', true));
});

test('an admin lets support in for 24 hours', function (): void {
    $this->post(route('settings.support.store'))->assertRedirect(route('settings.support.edit'));

    expect($this->marco->family->fresh()?->support_until?->equalTo(now()->addHours(24)))->toBeTrue();
});

test('the page says until when', function (): void {
    $this->post(route('settings.support.store'));

    $this->get(route('settings.support.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('support_until', now()->addHours(24)->toIso8601String())
        ->where('admin_level', 'Everything, like you'));
});

test('an expired permission reads as off', function (): void {
    $this->marco->family->forceFill(['support_until' => now()->subMinute()])->save();

    $this->get(route('settings.support.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('support_until', null));
});

test('an admin takes it back', function (): void {
    $this->post(route('settings.support.store'));

    $this->delete(route('settings.support.destroy'))->assertRedirect(route('settings.support.edit'));

    expect($this->marco->family->fresh()?->support_until)->toBeNull();
});

test('a member cannot change it but sees the state', function (): void {
    $member = User::factory()->for($this->marco->family)->create(['role' => Role::Member]);
    $this->actingAs($member);

    $this->post(route('settings.support.store'))->assertForbidden();
    $this->delete(route('settings.support.destroy'))->assertForbidden();
    expect($this->marco->family->fresh()?->support_until)->toBeNull();

    $this->get(route('settings.support.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('can_change', false));
});
