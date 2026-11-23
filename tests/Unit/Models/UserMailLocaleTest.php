<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\App;

test('the mails of an account are in its language', function (): void {
    $user = new User;
    $user->locale = 'it';

    expect($user->mailLocale())->toBe('it');
});

test('an account without a language gets the mails in the language of the app, not of the request', function (): void {
    config(['app.locale' => 'en']);
    App::setLocale('it');
    config(['app.locale' => 'en']);

    expect(new User()->mailLocale())->toBe('en');
});
