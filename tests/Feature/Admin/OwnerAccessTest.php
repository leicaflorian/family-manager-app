<?php

declare(strict_types=1);

use App\Models\Owner;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Auth\Pages\Login;
use Filament\Panel;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('sends a guest to the panel login', function (): void {
    get('/admin')->assertRedirect('/admin/login');
});

it('does not let an account of a family enter the panel', function (): void {
    $user = User::factory()->admin()->create(['email' => 'laura@example.com']);

    actingAs($user)->get('/admin')->assertRedirect('/admin/login');

    Livewire::test(Login::class)
        ->fillForm(['email' => 'laura@example.com', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email'])
        ->assertSee('These credentials do not match our records.');
});

it('lets an owner with the authenticator enter', function (): void {
    $owner = Owner::factory()->withAuthenticator()->create();

    actingAs($owner, 'owner')->get('/admin')->assertOk();
});

it('asks an owner without the authenticator to set it up', function (): void {
    $owner = Owner::factory()->create();

    actingAs($owner, 'owner')->get('/admin')
        ->assertRedirect()
        ->assertRedirectContains('/admin/multi-factor-authentication/set-up');
});

it('requires the authenticator outside the local environment', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $panel = new AdminPanelProvider(app())->panel(Panel::make());

    expect($panel->hasMultiFactorAuthentication())->toBeTrue()
        ->and($panel->isMultiFactorAuthenticationRequired())->toBeTrue();
});

it('does not ask for the authenticator in the local environment', function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    $panel = new AdminPanelProvider(app())->panel(Panel::make());

    expect($panel->hasMultiFactorAuthentication())->toBeFalse();
});
