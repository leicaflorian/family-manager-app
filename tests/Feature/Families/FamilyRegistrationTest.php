<?php

declare(strict_types=1);

use App\Enums\PersonRole;
use App\Enums\Role;
use App\Models\DocumentCategory;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\FamilySetting;
use App\Models\Person;
use App\Models\User;

/**
 * The data of the *Sign up* form for a person of the Bianchi family.
 *
 * @return array{first_name: string, last_name: string, email: string, password: string, terms: string}
 */
function signUp(string $firstName = 'Marco', string $lastName = 'Bianchi', string $email = 'marco@example.com'): array
{
    return [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'password' => 'a-long-password',
        'terms' => '1',
    ];
}

test('signing up creates the family, the admin account, the person and the categories', function (): void {
    $this->post(route('register.store'), signUp())->assertSessionHasNoErrors();

    $this->assertAuthenticated();
    $marco = User::query()->sole();
    $person = Person::query()->sole();
    $home = DocumentCategory::query()->where('name', 'Home and utilities')->sole();

    expect(Family::query()->sole()->name)->toBe('Bianchi')
        ->and($marco->role)->toBe(Role::Admin)
        ->and($marco->name)->toBe('Marco Bianchi')
        ->and($person->user_id)->toBe($marco->id)
        ->and($person->first_name)->toBe('Marco')
        ->and($person->last_name)->toBe('Bianchi')
        ->and($person->initials)->toBe('MB')
        ->and($person->role)->toBe(PersonRole::Parent)
        ->and(DocumentCategory::query()->whereNull('parent_id')->count())->toBe(10)
        ->and($home->children()->pluck('name')->all())
        ->toBe(['Electricity', 'Gas', 'Water', 'Internet', 'Phone', 'Waste tax (TARI)', 'TV licence'])
        ->and(ExpenseCategory::query()->whereNull('parent_id')->count())->toBe(12)
        ->and(FamilySetting::query()->count())->toBe(1);
});

test('a family signing up in Italian gets its categories in Italian', function (): void {
    $this->withHeader('Accept-Language', 'it')->post(route('register.store'), signUp())->assertSessionHasNoErrors();

    $home = DocumentCategory::query()->where('name', 'Casa e utenze')->sole();

    expect($home->children()->pluck('name')->all())
        ->toBe(['Luce', 'Gas', 'Acqua', 'Internet', 'Telefonia', 'TARI', 'Canone RAI']);
});

test('the categories of a new family do not touch those of another', function (): void {
    $this->post(route('register.store'), signUp())->assertSessionHasNoErrors();
    auth()->logout();
    $this->post(route('register.store'), signUp('Anna', 'Rossi', 'anna@example.com'))->assertSessionHasNoErrors();

    $anna = User::query()->where('email', 'anna@example.com')->sole();
    $categories = DocumentCategory::query()->get();

    expect($categories)->toHaveCount(DocumentCategory::withoutGlobalScopes()->count() / 2)
        ->and($categories->pluck('family_id')->unique()->all())->toBe([$anna->family_id])
        ->and($categories->whereNotNull('parent_id')->every(
            fn (DocumentCategory $c): bool => $categories->contains('id', $c->parent_id)
        ))->toBeTrue();
});

test('without accepting the terms nobody signs up', function (): void {
    $this->post(route('register.store'), [...signUp(), 'terms' => ''])
        ->assertSessionHasErrors('terms');

    $this->assertGuest();
    expect(Family::query()->count())->toBe(0);
});

test('the password has at least 10 characters, as the form says', function (): void {
    $this->post(route('register.store'), [...signUp(), 'password' => 'nine-cha9'])
        ->assertSessionHasErrors('password');

    $this->assertGuest();
});
