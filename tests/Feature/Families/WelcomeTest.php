<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\FamilySetting;
use App\Models\Home;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->post(route('register.store'), [
        'first_name' => 'Marco', 'last_name' => 'Bianchi', 'email' => 'marco@example.com',
        'password' => 'a-long-password', 'terms' => '1',
    ]);
    // The email verified: the Welcome comes after the verification.
    User::query()->update(['email_verified_at' => now()]);
    $this->actingAs(User::query()->sole());
});

test('a new account opening the app goes to the Welcome', function (): void {
    $this->get('/')->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertRedirect(route('welcome'));
    $this->get(route('welcome'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('welcome/Welcome')
        ->where('me.first_name', 'Marco')
        ->where('me.role_label', 'Adult / Parent')
        ->where('features.budget', false)
        ->where('features.mail', false)
        ->where('features.can_change', true));
});

test('after Done the home and the people are there and the Home opens', function (): void {
    $this->post(route('welcome.store'), [
        'people' => [
            ['first_name' => 'Laura', 'last_name' => 'Bianchi', 'role' => 'parent'],
            ['first_name' => 'Giulia', 'last_name' => 'Bianchi', 'role' => 'child'],
        ],
        'home' => ['name' => 'Casa di via Roma', 'address' => 'Via Roma 12', 'city' => 'Bologna', 'is_owned' => true],
    ])->assertRedirect(route('dashboard'));

    expect(Home::query()->sole()->address)->toBe('Via Roma 12')
        ->and(Person::query()->pluck('first_name')->all())->toBe(['Marco', 'Laura', 'Giulia'])
        ->and(Family::query()->sole()->welcomed_at)->not->toBeNull();
    $this->get(route('dashboard'))->assertOk();
});

test('the home can be left for later', function (): void {
    $this->post(route('welcome.store'), ['people' => []])->assertRedirect(route('dashboard'));

    expect(Home::query()->count())->toBe(0);
});

test('once the Welcome is done its page leads to the Home', function (): void {
    $this->post(route('welcome.store'), ['people' => []]);

    $this->get(route('welcome'))->assertRedirect(route('dashboard'));
});

test('the Welcome saves the birth date of whoever signs in and the tax code in capitals', function (): void {
    $this->post(route('welcome.store'), [
        'me' => ['birth_date' => '1980-03-12', 'tax_code' => null],
        'people' => [
            ['first_name' => 'Laura', 'last_name' => 'Bianchi', 'role' => 'parent', 'birth_date' => '1982-08-01', 'tax_code' => 'bnclra82m41a944k'],
        ],
    ])->assertRedirect(route('dashboard'));

    $marco = Person::query()->where('first_name', 'Marco')->sole();
    $laura = Person::query()->where('first_name', 'Laura')->sole();
    expect($marco->birth_date?->format('d/m/Y'))->toBe('12/03/1980')
        ->and($laura->tax_code)->toBe('BNCLRA82M41A944K')
        ->and($laura->birth_date?->format('d/m/Y'))->toBe('01/08/1982');
});

test('a birth date in the future is refused', function (): void {
    $tomorrow = now('Europe/Rome')->addDay()->toDateString();

    $this->postJson(route('welcome.store'), [
        'me' => ['birth_date' => $tomorrow],
        'people' => [['first_name' => 'Laura', 'last_name' => 'Bianchi', 'role' => 'parent', 'birth_date' => $tomorrow]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['me.birth_date', 'people.0.birth_date']);

    expect(Family::query()->sole()->welcomed_at)->toBeNull();
});

test('birth date and tax code may stay empty', function (): void {
    $this->post(route('welcome.store'), [
        'me' => ['birth_date' => '', 'tax_code' => ''],
        'people' => [['first_name' => 'Laura', 'last_name' => 'Bianchi', 'role' => 'parent', 'birth_date' => '', 'tax_code' => '']],
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

    expect(Person::query()->where('first_name', 'Laura')->sole()->tax_code)->toBeNull()
        ->and(Person::query()->where('first_name', 'Marco')->sole()->birth_date)->toBeNull();
});

test('turning on only the Budget leaves the mail off and without an address', function (): void {
    $this->post(route('welcome.store'), [
        'people' => [],
        'features' => ['budget' => true, 'mail' => false],
    ])->assertRedirect(route('dashboard'));

    $family = Family::query()->sole();
    expect($family->budget_enabled)->toBeTrue()
        ->and($family->mail_enabled)->toBeFalse()
        ->and(FamilySetting::query()->whereNotNull('mail_inbox_recipient')->exists())->toBeFalse();
});

test('the switches of whoever cannot manage the family are ignored', function (): void {
    User::query()->update(['role' => 'member']);
    $this->actingAs(User::query()->sole());

    $this->post(route('welcome.store'), [
        'people' => [],
        'features' => ['budget' => true, 'mail' => false],
    ])->assertRedirect(route('dashboard'));

    expect(Family::query()->sole()->budget_enabled)->toBeFalse();
});

test('a second Done changes nothing', function (): void {
    $this->post(route('welcome.store'), ['me' => ['birth_date' => '1980-03-12'], 'people' => []]);

    $this->post(route('welcome.store'), [
        'me' => ['birth_date' => '1990-01-01', 'tax_code' => 'XYZ'],
        'people' => [['first_name' => 'Laura', 'last_name' => 'Bianchi', 'role' => 'parent']],
        'home' => ['name' => 'Casa'],
        'features' => ['budget' => true, 'mail' => true],
    ])->assertRedirect(route('dashboard'));

    $family = Family::query()->sole();
    expect(Person::query()->pluck('first_name')->all())->toBe(['Marco'])
        ->and(Person::query()->sole()->birth_date?->format('d/m/Y'))->toBe('12/03/1980')
        ->and(Home::query()->count())->toBe(0)
        ->and($family->budget_enabled)->toBeFalse()
        ->and($family->mail_enabled)->toBeFalse();
});
