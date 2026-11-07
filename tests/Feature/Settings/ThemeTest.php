<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\Theme;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('saves the theme I choose', function (): void {
    $marco = registerFamily();

    $this->actingAs($marco)->put(route('account.theme'), ['theme' => 'dark'])->assertRedirect();

    expect($marco->fresh()->theme)->toBe(Theme::Dark);
});

it('refuses a theme that does not exist', function (): void {
    $this->actingAs(registerFamily())->put(route('account.theme'), ['theme' => 'blue'])->assertSessionHasErrors('theme');
});

it('lets a member choose their own theme', function (): void {
    $marco = registerFamily();
    $laura = User::factory()->for($marco->family)->create(['role' => Role::Member]);

    $this->actingAs($laura)->put(route('account.theme'), ['theme' => 'light'])->assertRedirect();

    expect($laura->fresh()->theme)->toBe(Theme::Light);
});

it('shares the theme of whoever signed in', function (): void {
    $marco = registerFamily();
    $marco->update(['theme' => Theme::Dark]);

    $this->actingAs($marco)->get(route('account.edit'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('theme', 'dark'));
});

it('shares the system theme to a guest', function (): void {
    $this->get(route('login'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('theme', 'system'));
});

it('starts every account on the system theme', function (): void {
    expect(registerFamily()->fresh()->theme)->toBe(Theme::System);
});
