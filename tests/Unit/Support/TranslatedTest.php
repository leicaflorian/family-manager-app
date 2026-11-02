<?php

declare(strict_types=1);

use App\Support\Translated;

test('it gives the text in the language of the app, with its placeholders', function (): void {
    app()->setLocale('it');

    expect(Translated::text('Upload it'))->toBe('Caricalo')
        ->and(Translated::text('Missing · :document', ['document' => 'Libretto']))->toBe('Manca · Libretto');
});

test('it gives the key itself when there is no translation, as the English app does', function (): void {
    app()->setLocale('en');

    expect(Translated::text('Upload it'))->toBe('Upload it');
});
