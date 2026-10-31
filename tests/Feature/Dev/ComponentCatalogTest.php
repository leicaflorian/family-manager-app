<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\BianchiFamilySeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(BianchiFamilySeeder::class);
    $this->actingAs(User::query()->where('email', 'marco.bianchi@example.com')->sole());
});

/**
 * Switches the application to the given environment for the next requests.
 */
function inEnvironment(string $environment): void
{
    app()->detectEnvironment(fn (): string => $environment);
}

test('in local the catalogue opens with nothing selected', function (): void {
    inEnvironment('local');

    $this->get('/dev/components')->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('dev/Components')
        ->where('selected', null));
});

test('in local the catalogue selects an existing component from the query string', function (): void {
    inEnvironment('local');

    $this->get('/dev/components?component=FmButton')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('dev/Components')
        ->where('selected', 'FmButton'));
});

test('in local an unknown component in the query string selects nothing', function (): void {
    inEnvironment('local');

    $this->get('/dev/components?component=FmNope')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('selected', null));
});

test('in local the frame of a component opens', function (): void {
    inEnvironment('local');

    $this->get('/dev/components/FmButton')->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('dev/ComponentFrame')
        ->where('component', 'FmButton'));
});

test('the frame of a missing or malformed component is a 404', function (string $path): void {
    inEnvironment('local');

    $this->get($path)->assertNotFound();
})->with(['/dev/components/FmNope', '/dev/components/..%2Fui']);

test('outside local both routes are a 404', function (string $environment, string $path): void {
    inEnvironment($environment);

    $this->get($path)->assertNotFound();
})->with(['testing', 'staging', 'production'])->with(['/dev/components', '/dev/components/FmButton']);

test('a guest goes to the login', function (): void {
    inEnvironment('local');
    auth()->logout();

    $this->get('/dev/components')->assertRedirect(route('login'));
});
