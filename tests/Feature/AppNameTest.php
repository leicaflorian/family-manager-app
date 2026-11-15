<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\LinkedDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('the default name is Fameja.app', function (): void {
    // Without APP_NAME (production may not set it) the app is not called «Laravel»
    expect(file_get_contents(config_path('app.php')))->toContain("'name' => env('APP_NAME', 'Fameja.app')");
});

test('the app is called Fameja.app', function (): void {
    expect(config('app.name'))->toBe('Fameja.app');
});

test('the linked phone share carries the name', function (): void {
    $family = Family::factory()->create();
    $user = User::factory()->for($family)->create();
    $device = LinkedDevice::factory()->for($family)->for($user)->create(['token_hash' => hash('sha256', 'x'), 'last_used_at' => now()]);

    $this->actingAs($user)->withSession(['linked_device_id' => $device->id])
        ->get(route('scan'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('name', 'Fameja.app'));
});
