<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\User;
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
