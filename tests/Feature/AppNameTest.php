<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\LinkedDevice;
use App\Models\User;
use App\Support\PageMeta;
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

test('the manifest says the name', function (): void {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe(config('app.name'))
        ->and($manifest['short_name'])->toBe(config('app.name'));
});

test('the offline page says the name', function (): void {
    $page = (string) file_get_contents(public_path('offline.html'));

    expect($page)->toContain('<title>'.config()->string('app.name').'</title>')
        ->and($page)->not->toContain('Family Manager');
});

test('the preview image is there', function (): void {
    $size = getimagesize(public_path('fameja-app-og-1200x630.png'));

    expect($size)->not->toBeFalse()
        ->and([$size[0] ?? null, $size[1] ?? null])->toBe([PageMeta::IMAGE_WIDTH, PageMeta::IMAGE_HEIGHT])
        ->and(file_exists(public_path('family-manager-og-1200x630.png')))->toBeFalse();
});
