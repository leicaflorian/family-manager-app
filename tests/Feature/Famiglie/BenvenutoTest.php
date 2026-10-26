<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\Home;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->post(route('register.store'), [
        'nome' => 'Marco', 'cognome' => 'Bianchi', 'email' => 'marco@example.com',
        'password' => 'una-password-lunga', 'condizioni' => '1',
    ]);
    // L'email confermata: il Benvenuto viene dopo la conferma.
    User::query()->update(['email_verified_at' => now()]);
    $this->actingAs(User::query()->sole());
});

test('un account nuovo che apre l\'app va al Benvenuto', function (): void {
    $this->get('/')->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertRedirect(route('benvenuto'));
    $this->get(route('benvenuto'))->assertInertia(fn (AssertableInertia $pagina): AssertableInertia => $pagina
        ->component('benvenuto/Benvenuto')
        ->where('persone.0.first_name', 'Marco'));
});

test('dopo Fatto la casa e le persone ci sono e si va alla Home', function (): void {
    $this->post(route('benvenuto.store'), [
        'persone' => [
            ['first_name' => 'Laura', 'last_name' => 'Bianchi', 'role' => 'parent'],
            ['first_name' => 'Giulia', 'last_name' => 'Bianchi', 'role' => 'child'],
        ],
        'casa' => ['name' => 'Casa di via Roma', 'address' => 'Via Roma 12', 'city' => 'Bologna', 'is_owned' => true],
    ])->assertRedirect(route('dashboard'));

    expect(Home::query()->sole()->address)->toBe('Via Roma 12')
        ->and(Person::query()->pluck('first_name')->all())->toBe(['Marco', 'Laura', 'Giulia'])
        ->and(Family::query()->sole()->welcomed_at)->not->toBeNull();
    $this->get(route('dashboard'))->assertOk();
});

test('la casa si può lasciare per dopo', function (): void {
    $this->post(route('benvenuto.store'), ['persone' => []])->assertRedirect(route('dashboard'));

    expect(Home::query()->count())->toBe(0);
});

test('a Benvenuto finito la pagina porta alla Home', function (): void {
    $this->post(route('benvenuto.store'), ['persone' => []]);

    $this->get(route('benvenuto'))->assertRedirect(route('dashboard'));
});
