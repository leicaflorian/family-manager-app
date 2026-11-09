<?php

declare(strict_types=1);

use App\Models\Owner;
use Illuminate\Support\Facades\Hash;

it('creates an owner from the answers', function (): void {
    $this->artisan('owner:create')
        ->expectsQuestion('Name', 'Florian')
        ->expectsQuestion('Email', 'owner@example.com')
        ->expectsQuestion('Password', 'a-long-secret-1')
        ->expectsQuestion('Confirm the password', 'a-long-secret-1')
        ->assertSuccessful();

    $owner = Owner::query()->where('email', 'owner@example.com')->firstOrFail();
    expect($owner->name)->toBe('Florian')
        ->and(Hash::check('a-long-secret-1', $owner->password))->toBeTrue();
});

it('refuses an email already used', function (): void {
    Owner::factory()->create(['email' => 'owner@example.com']);

    $this->artisan('owner:create')
        ->expectsQuestion('Name', 'Other')
        ->expectsQuestion('Email', 'owner@example.com')
        ->expectsQuestion('Password', 'a-long-secret-1')
        ->expectsQuestion('Confirm the password', 'a-long-secret-1')
        ->assertFailed();

    expect(Owner::query()->count())->toBe(1);
});
