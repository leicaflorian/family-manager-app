<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

test('without a preferred language the pages are in the default language', function (): void {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('locale', 'en'));
});

test('a browser that prefers Italian gets the pages in Italian', function (): void {
    $this->withHeader('Accept-Language', 'it-IT,it;q=0.9,en;q=0.8')
        ->get(route('login'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('locale', 'it'));
});

test('a language the app does not have falls back to the default one', function (): void {
    $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
        ->get(route('login'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('locale', 'en'));
});

test('the messages of a form follow the language of the browser', function (): void {
    $this->withHeader('Accept-Language', 'it')
        ->post(route('register.store'), [])
        ->assertSessionHasErrors(['first_name' => 'Il campo nome è richiesto.']);
});
