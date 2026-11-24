<?php

declare(strict_types=1);

use App\Actions\People\SavePerson;
use App\Enums\HomeStatus;
use App\Enums\PersonRole;
use App\Models\Family;
use App\Models\Home;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentFamily;

beforeEach(function (): void {
    $this->family = Family::factory()->create();
    $this->admin = User::factory()->for($this->family)->admin()->create(['name' => 'Marco Bianchi']);
});

test('a person saved without initials gets them from the name', function (): void {
    $person = CurrentFamily::using($this->family->id, fn (): Person => resolve(SavePerson::class)->handle([
        'first_name' => 'Laura', 'last_name' => 'Bianchi', 'role' => PersonRole::Parent,
    ]));

    expect($person->initials)->toBe('LB');
});

test('initials written by hand stay', function (): void {
    $person = CurrentFamily::using($this->family->id, fn (): Person => resolve(SavePerson::class)->handle([
        'first_name' => 'Laura', 'last_name' => 'Bianchi', 'role' => PersonRole::Parent, 'initials' => 'ZZ',
    ]));

    expect($person->initials)->toBe('ZZ');
});

test('the tax code is saved in upper case, on a new person and on a change', function (): void {
    [$new, $changed] = CurrentFamily::using($this->family->id, function (): array {
        $new = resolve(SavePerson::class)->handle([
            'first_name' => 'Laura', 'last_name' => 'Bianchi', 'role' => PersonRole::Parent, 'tax_code' => 'bnclra82m41a944k',
        ]);
        $changed = resolve(SavePerson::class)->handle([
            'first_name' => 'Laura', 'last_name' => 'Bianchi', 'role' => PersonRole::Parent, 'tax_code' => 'rssmra80a01h501u',
        ], $new);

        return [$new, $changed];
    });

    expect($new->fresh()?->tax_code)->not->toBeNull()
        ->and($changed->fresh()?->tax_code)->toBe('RSSMRA80A01H501U');
    expect(Person::query()->withoutGlobalScopes()->pluck('tax_code')->all())->toBe(['RSSMRA80A01H501U']);
});

test('a new person from the People form gets initials and an upper case tax code', function (): void {
    $this->actingAs($this->admin)->post(route('people.store'), [
        'first_name' => 'Laura', 'last_name' => 'Bianchi', 'role' => 'parent', 'tax_code' => 'bnclra82m41a944k',
    ])->assertSessionHasNoErrors();

    $person = Person::query()->withoutGlobalScopes()->sole();
    expect($person->initials)->toBe('LB')->and($person->tax_code)->toBe('BNCLRA82M41A944K');
});

test('the Welcome saves people with initials, refuses a long name and creates the home as a Home card', function (): void {
    $this->family->update(['welcomed_at' => null]);
    $this->actingAs($this->admin);
    $this->post(route('welcome.store'), [
        'people' => [['first_name' => str_repeat('a', 150), 'last_name' => 'Bianchi', 'role' => 'parent']],
    ])->assertSessionHasErrors('people.0.first_name');

    $this->post(route('welcome.store'), [
        'people' => [['first_name' => 'Giulia', 'last_name' => 'Bianchi', 'role' => 'child']],
        'home' => ['name' => 'Casa', 'address' => 'Via Roma 12', 'city' => 'Bologna', 'is_owned' => true],
    ])->assertSessionHasNoErrors();

    $home = Home::query()->withoutGlobalScopes()->sole();
    expect(Person::query()->withoutGlobalScopes()->sole()->initials)->toBe('GB')
        ->and($home->status)->toBe(HomeStatus::Active)
        ->and($home->country)->toBe(__('Italy'))
        ->and($home->is_owned)->toBeTrue();
});

test('the account name change keeps the initials of the person', function (): void {
    CurrentFamily::using($this->family->id, fn () => Person::factory()->for($this->family)->create([
        'first_name' => 'Marco', 'last_name' => 'Bianchi', 'user_id' => $this->admin->id,
    ]));

    $this->actingAs($this->admin)->patch(route('account.update'), [
        'first_name' => 'Paolo', 'last_name' => 'Verdi', 'email' => $this->admin->email,
    ])->assertSessionHasNoErrors();

    expect(Person::query()->withoutGlobalScopes()->sole()->initials)->toBe('PV');
});
