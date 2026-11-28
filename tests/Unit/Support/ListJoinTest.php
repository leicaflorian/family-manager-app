<?php

declare(strict_types=1);

use App\Support\ListJoin;
use Illuminate\Support\Facades\App;

afterEach(fn () => App::setLocale('en'));

test('one item stays as it is, two or more are joined with commas and the last «and»', function (): void {
    expect(ListJoin::of([]))->toBe('')
        ->and(ListJoin::of(['Luce']))->toBe('Luce')
        ->and(ListJoin::of(['Luce', 'Gas']))->toBe('Luce and Gas')
        ->and(ListJoin::of(['luce', 'gas', 'acqua'], 'it'))->toBe('luce, gas e acqua');
});

test('without a language it speaks the one of the app', function (): void {
    App::setLocale('it');

    expect(ListJoin::of(['Luce', 'Gas']))->toBe('Luce e Gas');
});
