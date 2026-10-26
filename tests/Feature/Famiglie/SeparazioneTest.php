<?php

declare(strict_types=1);

use App\Enums\Ruolo;
use App\Models\Family;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentFamily;

test('una famiglia non vede le persone di un\'altra', function (): void {
    $bianchi = Family::factory()->create(['name' => 'Bianchi']);
    $rossi = Family::factory()->create(['name' => 'Rossi']);
    $marco = Person::factory()->for($bianchi)->create(['first_name' => 'Marco']);
    $anna = Person::factory()->for($rossi)->create(['first_name' => 'Anna']);

    $this->actingAs(User::factory()->for($bianchi)->create());

    expect(Person::all()->pluck('id')->all())->toBe([$marco->id])
        ->and(Person::find($anna->id))->toBeNull();
});

test('family_id si compila da solo', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $persona = Person::create([
        'first_name' => 'Giulia',
        'last_name' => 'Bianchi',
        'initials' => 'GB',
        'role' => 'child',
    ]);

    expect($persona->family_id)->toBe($user->family_id)
        ->and($persona->family->is($user->family))->toBeTrue();
});

test('senza famiglia impostata non si legge niente', function (): void {
    Person::factory()->count(3)->create();

    expect(Person::count())->toBe(0);
});

test('fuori da una richiesta la famiglia si imposta e si toglie a mano', function (): void {
    $bianchi = Family::factory()->create();
    Person::factory()->for($bianchi)->count(2)->create();
    Person::factory()->create();

    CurrentFamily::set($bianchi->id);
    expect(CurrentFamily::id())->toBe($bianchi->id)
        ->and(Person::count())->toBe(2);

    CurrentFamily::clear();
    expect(CurrentFamily::id())->toBeNull()
        ->and(Person::count())->toBe(0);
});

test('un account è admin o member, e di base member', function (): void {
    $member = User::factory()->create();
    $admin = User::factory()->admin()->create();

    expect($member->role)->toBe(Ruolo::Member)
        ->and($member->isAdmin())->toBeFalse()
        ->and($admin->isAdmin())->toBeTrue()
        ->and($member->fresh()?->role)->toBe(Ruolo::Member);
});
