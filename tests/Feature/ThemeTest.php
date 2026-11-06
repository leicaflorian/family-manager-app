<?php

declare(strict_types=1);

use App\Enums\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves the page with the script that applies the dark theme before it is drawn', function (): void {
    $html = $this->get(route('login'))->assertOk()->getContent();

    expect($html)->toBeString();

    $head = (string) str($html)->between('<head>', '</head>');

    // Tests run with withoutVite(), so the Vite tags are absent: the stylesheet-free
    // <link rel="icon"> that precedes @vite marks the point the script must come before.
    $scriptAt = strpos($head, 'prefers-color-scheme: dark');
    $firstLinkAt = strpos($head, '<link');

    expect($scriptAt)->toBeInt()
        ->and($head)->toContain('<script>')
        ->and($head)->toContain('matchMedia')
        ->and($head)->toContain("document.documentElement.classList.add('dark')")
        ->and($scriptAt)->toBeLessThan($firstLinkAt);
});

it('draws the page dark when my theme is dark', function (): void {
    $marco = registerFamily();
    $marco->update(['theme' => Theme::Dark]);

    $html = $this->actingAs($marco)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->toMatch('/<html[^>]*class="dark"/');
});

it('lets the device decide when my theme is system', function (): void {
    $html = $this->actingAs(registerFamily())->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->not->toMatch('/<html[^>]*class="[^"]*dark/')
        ->and($html)->toContain('prefers-color-scheme: dark')
        ->and($html)->toMatch("/if \\(\\s*'system'\\s*===\\s*'system'/");
});

it('never lets the device darken a page when my theme is light', function (): void {
    $marco = registerFamily();
    $marco->update(['theme' => Theme::Light]);

    $html = $this->actingAs($marco)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->not->toMatch('/<html[^>]*class="[^"]*dark/')
        ->and($html)->toMatch("/if \\(\\s*'light'\\s*===\\s*'system'/");
});
