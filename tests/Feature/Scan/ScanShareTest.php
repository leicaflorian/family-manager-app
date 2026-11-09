<?php

declare(strict_types=1);

use App\Models\File;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

it('sends the POST of Share to the Scan page, as the fallback of the service worker', function (): void {
    $this->post(route('scan.share'), [
        'files' => [UploadedFile::fake()->create('pagella.pdf', 20, 'application/pdf')],
    ])->assertStatus(303)->assertRedirect(route('scan'));
});

it('accepts nothing and keeps nothing on the server', function (): void {
    $response = $this->post(route('scan.share'), [
        'files' => [UploadedFile::fake()->create('pagella.pdf', 20, 'application/pdf')],
    ]);

    $response->assertCookieMissing(config()->string('session.cookie'));
    expect(File::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('stays outside the web group: the system menu sends no CSRF token, and the route asks for none', function (): void {
    $route = Route::getRoutes()->getByName('scan.share');
    $middleware = $route === null ? [] : resolve('router')->gatherRouteMiddleware($route);

    expect($route?->uri())->toBe('scan/share')
        ->and($middleware)->not->toContain(PreventRequestForgery::class)
        ->and($middleware)->not->toContain(StartSession::class);
});

it('serves the service worker from the root, as a script that may control the whole site', function (): void {
    $built = public_path('build/sw.js');
    $created = ! Filesystem::exists($built);

    if ($created) {
        Filesystem::ensureDirectoryExists(dirname($built));
        Filesystem::put($built, '// the service worker');
    }

    try {
        $response = $this->get('/sw.js');

        $response->assertOk()
            ->assertHeader('Service-Worker-Allowed', '/')
            ->assertHeader('Content-Type', 'text/javascript; charset=utf-8');
        expect($response->headers->get('Cache-Control'))->toContain('no-cache');
        $response->assertCookieMissing(config()->string('session.cookie'));
    } finally {
        // Only the file this test wrote: a real build is left alone
        if ($created) {
            Filesystem::delete($built);
        }
    }
});

it('answers 404 for the service worker when the frontend was not built', function (): void {
    $built = public_path('build/sw.js');

    if (Filesystem::exists($built)) {
        $this->markTestSkipped('A real build is there.');
    }

    $this->get('/sw.js')->assertNotFound();
});
