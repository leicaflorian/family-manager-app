<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\LinkedDevice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->family = Family::factory()->create(['name' => 'Bianchi']);
    $this->laura = User::factory()->for($this->family)->create(['name' => 'Laura', 'email' => 'laura@example.com', 'password' => 'the-old-password']);
});

it('sets the new password of an account and names whose account it is', function (): void {
    $this->artisan('user:password', ['email' => 'laura@example.com'])
        ->expectsQuestion('New password', 'a-new-password-1')
        ->expectsQuestion('Confirm the password', 'a-new-password-1')
        ->expectsOutputToContain('Account of Laura, family Bianchi.')
        ->assertSuccessful();

    expect(Hash::check('a-new-password-1', $this->laura->fresh()->password))->toBeTrue();
});

it('signs out the linked phones of the account', function (): void {
    LinkedDevice::factory()->for($this->family)->for($this->laura)->create();

    $this->artisan('user:password', ['email' => 'laura@example.com'])
        ->expectsQuestion('New password', 'a-new-password-1')
        ->expectsQuestion('Confirm the password', 'a-new-password-1')
        ->assertSuccessful();

    expect(LinkedDevice::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('refuses an email without an account', function (): void {
    $this->artisan('user:password', ['email' => 'nobody@example.com'])
        ->expectsOutputToContain('No account')
        ->assertFailed();
});

it('refuses two passwords that do not match', function (): void {
    $this->artisan('user:password', ['email' => 'laura@example.com'])
        ->expectsQuestion('New password', 'a-new-password-1')
        ->expectsQuestion('Confirm the password', 'another-password-2')
        ->expectsOutputToContain('do not match')
        ->assertFailed();

    expect(Hash::check('the-old-password', $this->laura->fresh()->password))->toBeTrue();
});

it('refuses a password shorter than the rules of the app', function (): void {
    $this->artisan('user:password', ['email' => 'laura@example.com'])
        ->expectsQuestion('New password', 'short')
        ->expectsQuestion('Confirm the password', 'short')
        ->expectsOutputToContain('10')
        ->assertFailed();

    expect(Hash::check('the-old-password', $this->laura->fresh()->password))->toBeTrue();
});
