<?php

declare(strict_types=1);

use App\Actions\Calendar\ResetCalendarLink;
use App\Actions\Calendar\TurnOffCalendar;
use App\Actions\Calendar\TurnOnCalendar;
use App\Models\Family;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('turning on gives a secret of 40 letters and digits, kept with its fingerprint and the language', function (): void {
    $family = Family::factory()->create();

    $token = resolve(TurnOnCalendar::class)->handle($family, 'it');
    $family->refresh();

    expect($token)->toMatch('/^[A-Za-z0-9]{40}$/')
        ->and($family->calendar_token)->toBe($token)
        ->and($family->calendar_token_hash)->toBe(hash('sha256', $token))
        ->and($family->calendar_locale)->toBe('it');
});

test('turning on a calendar already on keeps its secret and its language', function (): void {
    $family = Family::factory()->create();
    $token = resolve(TurnOnCalendar::class)->handle($family, 'it');

    expect(resolve(TurnOnCalendar::class)->handle($family->refresh(), 'en'))->toBe($token)
        ->and($family->refresh()->calendar_locale)->toBe('it');
});

test('turning off forgets the secret, its fingerprint and the language', function (): void {
    $family = Family::factory()->create();
    resolve(TurnOnCalendar::class)->handle($family, 'en');

    resolve(TurnOffCalendar::class)->handle($family->refresh());
    $family->refresh();

    expect($family->calendar_token)->toBeNull()
        ->and($family->calendar_token_hash)->toBeNull()
        ->and($family->calendar_locale)->toBeNull();
});

test('turning on again after turning off gives another secret', function (): void {
    $family = Family::factory()->create();
    $first = resolve(TurnOnCalendar::class)->handle($family, 'en');
    resolve(TurnOffCalendar::class)->handle($family->refresh());

    expect(resolve(TurnOnCalendar::class)->handle($family->refresh(), 'en'))->not->toBe($first);
});

test('resetting the link gives another secret and keeps the language', function (): void {
    $family = Family::factory()->create();
    $first = resolve(TurnOnCalendar::class)->handle($family, 'it');

    $second = resolve(ResetCalendarLink::class)->handle($family->refresh());
    $family->refresh();

    expect($second)->toMatch('/^[A-Za-z0-9]{40}$/')->not->toBe($first)
        ->and($family->calendar_token)->toBe($second)
        ->and($family->calendar_token_hash)->toBe(hash('sha256', $second))
        ->and($family->calendar_locale)->toBe('it');
});

test('turning on takes only a language of the app', function (): void {
    $family = Family::factory()->create();

    expect(fn (): string => resolve(TurnOnCalendar::class)->handle($family, 'fr'))->toThrow(InvalidArgumentException::class);
    expect($family->refresh()->calendar_token_hash)->toBeNull();
});
