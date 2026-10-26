<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('le pagine ricevono il nome della famiglia per la barra laterale', function (): void {
    $this->actingAs(User::factory()->for(Family::factory()->create(['name' => 'Bianchi']))->create());

    $this->get(route('aree'))->assertInertia(fn (AssertableInertia $pagina): AssertableInertia => $pagina
        ->component('Aree')
        ->where('famiglia.name', 'Bianchi'));
});

test('senza accesso le pagine dell\'app portano al login', function (string $rotta): void {
    $this->get(route($rotta))->assertRedirect(route('login'));
})->with(['aree', 'documenti.index', 'famiglia', 'carica', 'impostazioni']);
