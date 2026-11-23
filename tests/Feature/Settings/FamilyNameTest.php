<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Family;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->family = Family::factory()->create(['name' => 'Bianchi']);
    $this->admin = User::factory()->for($this->family)->create(['role' => Role::Admin, 'email_verified_at' => now()]);
    $this->member = User::factory()->for($this->family)->create(['role' => Role::Member, 'email_verified_at' => now()]);
});

test('the page shows the name and whether who looks can change it', function (): void {
    $this->actingAs($this->admin)->get(route('settings.family.edit'))->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('settings/Family')
        ->where('settings.name', 'Bianchi')
        ->where('settings.can_edit', true));

    $this->actingAs($this->member)->get(route('settings.family.edit'))->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('settings.name', 'Bianchi')
        ->where('settings.can_edit', false));
});

test('an admin changes the name, trimmed', function (): void {
    $this->actingAs($this->admin)->put(route('settings.family.update'), ['name' => '  Bianchi Rossi  '])
        ->assertRedirect(route('settings.family.edit'));

    expect($this->family->fresh()->name)->toBe('Bianchi Rossi');
});

test('the name does not need to be unique', function (): void {
    Family::factory()->create(['name' => 'Rossi']);

    $this->actingAs($this->admin)->put(route('settings.family.update'), ['name' => 'Rossi'])->assertSessionHasNoErrors();

    expect($this->family->fresh()->name)->toBe('Rossi');
});

test('a member cannot change the name', function (): void {
    $this->actingAs($this->member)->put(route('settings.family.update'), ['name' => 'Verdi'])->assertForbidden();

    expect($this->family->fresh()->name)->toBe('Bianchi');
});

test('the name is required and not too long', function (mixed $name): void {
    $this->actingAs($this->admin)->put(route('settings.family.update'), ['name' => $name])->assertSessionHasErrors('name');

    expect($this->family->fresh()->name)->toBe('Bianchi');
})->with([[''], ['   '], [null], [str_repeat('a', 256)], [['Rossi']]]);

test('only the family of who signed in changes', function (): void {
    $other = Family::factory()->create(['name' => 'Verdi']);

    $this->actingAs($this->admin)->put(route('settings.family.update'), ['name' => 'Bianchi Rossi']);

    expect($other->fresh()->name)->toBe('Verdi');
});

test('a guest is sent to sign in', function (): void {
    $this->get(route('settings.family.edit'))->assertRedirect(route('login'));
    $this->put(route('settings.family.update'), ['name' => 'Verdi'])->assertRedirect(route('login'));
});
