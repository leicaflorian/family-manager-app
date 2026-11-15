<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('log in and sign up can be indexed, with the canonical address without the query', function (string $path): void {
    $response = $this->get($path.'?ref=x');

    $url = url($path);
    $response->assertOk()
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('<link rel="canonical" href="'.$url.'">', false)
        ->assertSee('<meta property="og:url" content="'.$url.'">', false)
        ->assertDontSee('name="robots"', false);
})->with(['/login', '/register']);

test('the other public pages are not indexed', function (string $path): void {
    $response = $this->get($path);

    $response->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertDontSee('rel="canonical"', false);
})->with(['/forgot-password', '/reset-password/abc', '/invitation/missing', '/connect']);

test('the pages of a family are not indexed', function (): void {
    $this->actingAs(User::factory()->for(Family::factory()->create())->create());

    $this->get('/dashboard')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('the head is written in the language of the browser', function (): void {
    $this->withHeader('Accept-Language', 'it')->get('/login')
        ->assertSee('<title>Accedi - Family Manager</title>', false)
        ->assertSee('<meta property="og:title" content="Accedi - Family Manager">', false)
        ->assertSee('<meta name="description" content="Le scadenze, le bollette e i documenti di tutta la famiglia, in un posto solo.">', false)
        ->assertSee('<meta property="og:locale" content="it_IT">', false);

    $this->withHeader('Accept-Language', 'en')->get('/login')
        ->assertSee('<title>Log in - Family Manager</title>', false)
        ->assertSee('<meta property="og:locale" content="en_US">', false);
});

test('a shared link shows the preview image', function (): void {
    $this->get('/login')
        ->assertSee('<meta property="og:image" content="'.url('/family-manager-og-1200x630.png').'">', false)
        ->assertSee('<meta property="og:image:width" content="1200">', false)
        ->assertSee('<meta property="og:image:height" content="630">', false)
        ->assertSee('<meta property="og:site_name" content="Family Manager">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
});

test('a page without a route has the default head, not indexed', function (): void {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});
