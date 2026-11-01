<?php

declare(strict_types=1);

use App\Support\Ics\Calendar;
use App\Support\Ics\CalendarEvent;
use Carbon\CarbonImmutable;

// The titles that reminders copied from Directus have: «what to do · scade il dd/mm/yyyy · manca la spesa prevista».
dataset('reminder titles', [
    'vehicles.road_tax_due_date' => 'Pagare il bollo · Panda',
    'vehicles.inspection_due_date' => 'Prenotare la revisione · Panda',
    'insurance_policies.expiry_date' => 'Rinnovo polizza · Auto · Assicura',
    'belongings.warranty_expiry_date' => 'Fine garanzia · Lavatrice',
    'documents.expiry_date' => "Documento in scadenza · Carta d'identità",
    'filings.due_date' => 'Presentare la pratica · 730',
    'health_events.next_checkup_date' => 'Prenotare il controllo · Visita oculistica',
    'subscriptions.renewal_date' => 'Rinnovo abbonamento · Spotify',
    'subscriptions.trial_end_date' => 'Fine periodo di prova · Netflix',
    'loans.start_date' => 'Prima rata · Auto nuova',
    'loans.end_date' => 'Ultima rata · Auto nuova',
]);

it('takes the deadline and the missing expense note out of the title', function (string $base): void {
    expect(Calendar::withoutDeadline($base.' · scade il 31/01/2027'))->toBe($base)
        ->and(Calendar::withoutDeadline($base.' · scade il 31/01/2027 · manca la spesa prevista'))->toBe($base)
        ->and(Calendar::missesExpense($base.' · scade il 31/01/2027 · manca la spesa prevista'))->toBeTrue()
        ->and(Calendar::missesExpense($base.' · scade il 31/01/2027'))->toBeFalse();
})->with('reminder titles');

it('leaves a title without a deadline as it is', function (): void {
    expect(Calendar::withoutDeadline('Comprare il latte'))->toBe('Comprare il latte')
        ->and(Calendar::missesExpense('Comprare il latte'))->toBeFalse();
});

it('escapes commas, semicolons, backslashes and new lines', function (): void {
    expect(Calendar::escapeText("a,b;c\\d\ne\r\nf"))->toBe('a\,b\;c\\\\d\ne\nf');
});

it('folds at 75 bytes without cutting a letter', function (): void {
    $line = str_repeat('a', 74).'è'.str_repeat('b', 10);

    $lines = explode("\r\n", Calendar::fold($line));

    expect($lines)->toHaveCount(2)
        ->and(strlen($lines[0]))->toBe(74)
        ->and($lines[1])->toBe(' èbbbbbbbbbb')
        ->and(str_replace("\r\n ", '', Calendar::fold($line)))->toBe($line);

    foreach (explode("\r\n", Calendar::fold(str_repeat('è', 100))) as $part) {
        expect(strlen($part))->toBeLessThanOrEqual(75);
    }
});

function eventOn(string $date): CalendarEvent
{
    return new CalendarEvent('reminder-1@familymanager', CarbonImmutable::parse($date), 'Pagare il bollo · Panda', 'Manca la spesa');
}

it('ends the all-day event the day after, so 31/12/2026 ends on 01/01/2027', function (): void {
    $text = Calendar::render('Famiglia', [eventOn('2026-12-31')]);

    expect($text)->toContain("DTSTART;VALUE=DATE:20261231\r\n")
        ->and($text)->toContain("DTEND;VALUE=DATE:20270101\r\n")
        ->and($text)->toContain("UID:reminder-1@familymanager\r\n")
        ->and($text)->toContain("SUMMARY:Pagare il bollo · Panda\r\n");
});

it('ends every line with CRLF', function (): void {
    $text = Calendar::render('Famiglia, Bianchi', [eventOn('2026-12-31')]);

    expect($text)->toStartWith("BEGIN:VCALENDAR\r\n")
        ->and($text)->toEndWith("END:VCALENDAR\r\n")
        ->and(preg_match('/(?<!\r)\n/', $text))->toBe(0)
        ->and($text)->toContain('X-WR-CALNAME:Famiglia\, Bianchi');
});

it('has REFRESH-INTERVAL and X-PUBLISHED-TTL of one hour, transparent all-day events', function (): void {
    $text = Calendar::render('Famiglia', [eventOn('2026-12-31')]);

    expect($text)->toContain("REFRESH-INTERVAL;VALUE=DURATION:PT1H\r\n")
        ->and($text)->toContain("X-PUBLISHED-TTL:PT1H\r\n")
        ->and($text)->toContain("TRANSP:TRANSPARENT\r\n")
        ->and($text)->toContain('DTSTART;VALUE=DATE:');
});

it('renders an empty calendar', function (): void {
    expect(Calendar::render('Famiglia', []))->not->toContain('BEGIN:VEVENT');
});
