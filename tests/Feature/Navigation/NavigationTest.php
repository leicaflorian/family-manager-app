<?php

declare(strict_types=1);

use App\Enums\Area;
use App\Models\Family;
use App\Models\User;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

test('the pages receive the name of the family for the sidebar', function (): void {
    $this->actingAs(User::factory()->for(Family::factory()->create(['name' => 'Bianchi']))->create());

    $this->get(route('areas'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('Areas')
        ->where('family.name', 'Bianchi'));
});

test('without signing in the pages of the app lead to Sign in', function (string $route): void {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['areas', 'documents.index', 'family', 'upload', 'settings']);

test('the pages receive the active cards of each area for the sidebar', function (): void {
    Storage::fake('local');
    $this->seed(BianchiFamilySeeder::class);
    $this->actingAs(User::query()->where('email', 'marco.bianchi@example.com')->sole());

    $this->get(route('documents.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('areas', count(Area::cases()))
        ->where('areas.0.area', 'home')
        ->where('areas.0.count', 7)
        ->where('areas.1.area', 'vehicles')
        ->where('areas.1.count', 1));
});
