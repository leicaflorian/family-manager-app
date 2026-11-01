<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('the locale of the browser is saved once', function (): void {
    $user = User::factory()->for(Family::factory()->create())->create();
    $this->actingAs($user);

    $this->withHeader('Accept-Language', 'it-IT,it;q=0.9')->get('/dashboard');
    expect($user->fresh()->locale)->toBe('it');

    $updates = [];
    DB::listen(function ($query) use (&$updates): void {
        if (str_starts_with($query->sql, 'update `users`')) {
            $updates[] = $query->sql;
        }
    });

    $this->withHeader('Accept-Language', 'it')->get('/dashboard');
    expect($updates)->toHaveCount(0);

    $this->withHeader('Accept-Language', 'en')->get('/dashboard');
    expect($updates)->toHaveCount(1)
        ->and($user->fresh()->locale)->toBe('en');
});

test('a guest request saves nothing', function (): void {
    $this->withHeader('Accept-Language', 'it')->get('/');

    expect(User::query()->whereNotNull('locale')->count())->toBe(0);
});
