<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Family;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentFamily;

test('a family does not see the people of another', function (): void {
    $bianchi = Family::factory()->create(['name' => 'Bianchi']);
    $rossi = Family::factory()->create(['name' => 'Rossi']);
    $marco = Person::factory()->for($bianchi)->create(['first_name' => 'Marco']);
    $anna = Person::factory()->for($rossi)->create(['first_name' => 'Anna']);

    $this->actingAs(User::factory()->for($bianchi)->create());

    expect(Person::all()->pluck('id')->all())->toBe([$marco->id])
        ->and(Person::find($anna->id))->toBeNull();
});

test('family_id is filled in automatically', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $person = Person::create([
        'first_name' => 'Giulia',
        'last_name' => 'Bianchi',
        'initials' => 'GB',
        'role' => 'child',
    ]);

    expect($person->family_id)->toBe($user->family_id)
        ->and($person->family->is($user->family))->toBeTrue();
});

test('without a family set nothing is read', function (): void {
    Person::factory()->count(3)->create();

    expect(Person::count())->toBe(0);
});

test('outside a request the family is set and removed by hand', function (): void {
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

test('an account is admin or member, and member by default', function (): void {
    $member = User::factory()->create();
    $admin = User::factory()->admin()->create();

    expect($member->role)->toBe(Role::Member)
        ->and($member->isAdmin())->toBeFalse()
        ->and($admin->isAdmin())->toBeTrue()
        ->and($member->fresh()?->role)->toBe(Role::Member);
});
