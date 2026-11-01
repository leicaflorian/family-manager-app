<?php

declare(strict_types=1);

use App\Support\Reminders\DueText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;

/**
 * A day in Rome.
 *
 * @param  string  $day  the day as `aaaa-mm-gg`
 */
function romeDay(string $day): CarbonImmutable
{
    return CarbonImmutable::parse($day, 'Europe/Rome');
}

test('the words of a due date, as whenText of Directus', function (): void {
    App::setLocale('it');
    $today = romeDay('2026-10-14');

    expect(DueText::when($today, romeDay('2026-10-14'), false))->toBe('Scade oggi')
        ->and(DueText::when($today, romeDay('2026-10-15'), false))->toBe('Scade domani')
        ->and(DueText::when($today, romeDay('2026-10-21'), false))->toBe('Scade tra 7 giorni, il 21/10/2026')
        ->and(DueText::when($today, romeDay('2026-10-13'), false))->toBe('Scaduto ieri, il 13/10/2026')
        ->and(DueText::when($today, romeDay('2026-10-07'), false))->toBe('Scaduto da 7 giorni, il 07/10/2026');
});

test('the words of a reminder written by hand', function (): void {
    App::setLocale('it');
    $today = romeDay('2026-10-14');

    expect(DueText::when($today, romeDay('2026-10-14'), true))->toBe('Entro oggi')
        ->and(DueText::when($today, romeDay('2026-10-15'), true))->toBe('Entro domani')
        ->and(DueText::when($today, romeDay('2026-10-21'), true))->toBe('Entro il 21/10/2026, tra 7 giorni')
        ->and(DueText::when($today, romeDay('2026-10-13'), true))->toBe('Scaduto ieri, il 13/10/2026');
});

test('the words come in English too, with the same date format', function (): void {
    App::setLocale('en');

    expect(DueText::when(romeDay('2026-10-14'), romeDay('2026-10-21'), false))->toBe('Due in 7 days, on 21/10/2026')
        ->and(DueText::when(romeDay('2026-10-14'), romeDay('2026-10-14'), false))->toBe('Due today');
});

test('the days are counted on the calendar, also across the change of the clock', function (): void {
    App::setLocale('it');

    // Summer time starts on 29/03/2026 and ends on 25/10/2026: a day is still a day
    expect(DueText::when(romeDay('2026-03-28'), romeDay('2026-03-30'), false))->toBe('Scade tra 2 giorni, il 30/03/2026')
        ->and(DueText::when(romeDay('2026-10-24'), romeDay('2026-10-26'), false))->toBe('Scade tra 2 giorni, il 26/10/2026')
        ->and(DueText::when(romeDay('2026-10-26'), romeDay('2026-10-24'), false))->toBe('Scaduto da 2 giorni, il 24/10/2026');
});

test('a time of the day does not change the words', function (): void {
    App::setLocale('it');

    // 00:30 in Rome in summer is 22:30 UTC of the day before: the caller gives the day in Rome
    $afterMidnight = CarbonImmutable::parse('2026-10-15 00:30', 'Europe/Rome');
    $beforeMidnight = CarbonImmutable::parse('2026-10-14 23:30', 'Europe/Rome');

    expect(DueText::when($afterMidnight, romeDay('2026-10-15'), false))->toBe('Scade oggi')
        ->and(DueText::when($beforeMidnight, romeDay('2026-10-15'), false))->toBe('Scade domani')
        ->and(DueText::when($afterMidnight->utc(), romeDay('2026-10-15'), false))->toBe('Scade oggi');
});

test('the start of the subject of the mail', function (): void {
    App::setLocale('it');
    $today = romeDay('2026-10-14');

    expect(DueText::subject($today, romeDay('2026-10-20')))->toBe('Promemoria')
        ->and(DueText::subject($today, romeDay('2026-10-14')))->toBe('Scade oggi')
        ->and(DueText::subject($today, romeDay('2026-10-13')))->toBe('Scaduto');
});
