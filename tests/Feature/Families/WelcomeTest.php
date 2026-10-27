<?php

declare(strict_types=1);

use App\Models\Family;
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
        ->where('people.0.first_name', 'Marco'));
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
