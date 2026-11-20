<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\LinkedDevice;
use App\Models\User;
use App\Support\PageMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('the default name is Fameja.app', function (): void {
    // Without APP_NAME (production may not set it) the app is not called «Laravel»
    expect(file_get_contents(config_path('app.php')))->toContain("'name' => env('APP_NAME', 'Fameja.app')");
});

test('mails go out with the name of the app when the sender name is not set', function (): void {
    // Without MAIL_FROM_NAME and APP_NAME the sender is not «Laravel»
    expect(file_get_contents(config_path('mail.php')))->toContain("env('MAIL_FROM_NAME', env('APP_NAME', 'Fameja.app'))");
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

test('the manifests say the name', function (string $file): void {
    $manifest = json_decode((string) file_get_contents(public_path($file)), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe(config('app.name'))
        ->and($manifest['short_name'])->toBe(config('app.name'));
})->with(['manifest.webmanifest', 'manifest-ios.webmanifest']);

test('the manifest of iPhone and iPad keeps the whole site in the app and starts where the icon was added', function (): void {
    $manifest = json_decode((string) file_get_contents(public_path('manifest-ios.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    // Without a manifest the scope of the icon is the folder of its page, and outside it Safari shows its bars;
    // without a start address the icon opens the page it was added from, with the secret of the phone after the #
    expect($manifest['scope'])->toBe('/')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest)->not->toHaveKey('start_url');
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

test('nothing says Family Manager, except the scanner agent', function (): void {
    $paths = [
        ...File::allFiles(app_path()),
        ...File::allFiles(config_path()),
        ...File::allFiles(lang_path()),
        ...array_filter(
            File::allFiles(resource_path()),
            // generated types, the tests (they check the old name is gone) and the copy of the design system, which keeps its own name
            fn (SplFileInfo $file): bool => ! str_starts_with($file->getPathname(), resource_path('js/generated'))
                && ! str_ends_with($file->getFilename(), '.test.ts')
                && $file->getPathname() !== resource_path('design/tokens.json'),
        ),
    ];
    $files = [
        ...array_map(fn (SplFileInfo $file): string => $file->getPathname(), $paths),
        public_path('manifest.webmanifest'),
        public_path('manifest-ios.webmanifest'),
        public_path('offline.html'),
        base_path('Dockerfile'),
        base_path('composer.json'),
    ];

    // The agent of the scanner keeps its name for now (decision of the owner, 15/11/2026)
    $left = array_values(array_filter(
        $files,
        fn (string $file): bool => preg_match('/Family Manager(?! Agent)/', (string) file_get_contents($file)) === 1,
    ));

    expect(array_map(fn (string $file): string => str_replace(base_path().'/', '', $file), $left))->toBe([]);
});
