<?php

declare(strict_types=1);

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
