<?php

declare(strict_types=1);

use App\Support\Mail\MailAddress;

test('slug', function (): void {
    expect(MailAddress::localPart('Leica'))->toBe('leica')
        ->and(MailAddress::localPart('De Luca'))->toBe('deluca')
        ->and(MailAddress::localPart("D'Angelo"))->toBe('dangelo')
        ->and(MailAddress::localPart('Müller'))->toBe('muller')
        ->and(MailAddress::localPart('Abcdefghijklmnopqrstuvwxyzabcd'))->toBe('abcdefghijklmnopqrst')
        ->and(MailAddress::localPart('Søren'))->toBe('soren')
        ->and(MailAddress::localPart('Weiß'))->toBe('weiss')
        ->and(MailAddress::localPart('Łukasz Æbel'))->toBe('lukaszaebel')
        ->and(MailAddress::localPart('Иванов'))->toBe('famiglia')
        ->and(MailAddress::localPart('  '))->toBe('famiglia');
});

test('randomPart', function (): void {
    expect(MailAddress::CHARACTERS)->toBe('abcdefghjkmnpqrstuvwxyz23456789')
        ->and(strlen(MailAddress::CHARACTERS))->toBe(31);

    for ($i = 0; $i < 200; $i++) {
        expect(MailAddress::make('x', 'mg.x.dev'))->toMatch('/^x\.[a-hjkmnp-z2-9]{6}@mg\.x\.dev$/');
    }
});

test('makeAddress', function (): void {
    expect(MailAddress::make('De Luca', 'mg.leicaflorianrobert.dev'))
        ->toMatch('/^deluca\.[a-hjkmnp-z2-9]{6}@mg\.leicaflorianrobert\.dev$/');
});
