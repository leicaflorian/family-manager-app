<?php

declare(strict_types=1);

use App\Data\AgendaEntryData;
use App\Support\AgendaEntries;

/**
 * An entry of the Agenda with only what the lists look at.
 */
function agendaEntriesEntry(string $kind, string $date, ?float $amount = null): AgendaEntryData
{
    return new AgendaEntryData("{$kind}:{$date}", $kind, $date, 'Title', '', 'Name', 'Detail', 'general', null, null, $amount, false, false, null, '/');
}

beforeEach(function (): void {
    $this->entries = [
        agendaEntriesEntry('payment', '2026-11-20', 10.1),
        agendaEntriesEntry('deadline', '2026-11-21'),
        agendaEntriesEntry('income', '2026-11-22', 500.0),
        agendaEntriesEntry('payment', '2026-11-25', 20.2),
        agendaEntriesEntry('task', '2026-11-26'),
        agendaEntriesEntry('income', '2026-11-27', 300.0),
    ];
});

test('the payments, the incomes and everything but the incomes, in their order', function (): void {
    expect(array_map(fn (AgendaEntryData $entry): string => $entry->key, AgendaEntries::payments($this->entries)))->toBe(['payment:2026-11-20', 'payment:2026-11-25'])
        ->and(array_map(fn (AgendaEntryData $entry): string => $entry->key, AgendaEntries::incomes($this->entries)))->toBe(['income:2026-11-22', 'income:2026-11-27'])
        ->and(array_map(fn (AgendaEntryData $entry): string => $entry->key, AgendaEntries::withoutIncomes($this->entries)))->toBe(['payment:2026-11-20', 'deadline:2026-11-21', 'payment:2026-11-25', 'task:2026-11-26']);
});

test('the entries before a day and from that day on, in their order', function (): void {
    expect(array_map(fn (AgendaEntryData $entry): string => $entry->date, AgendaEntries::before($this->entries, '2026-11-25')))->toBe(['2026-11-20', '2026-11-21', '2026-11-22'])
        ->and(array_map(fn (AgendaEntryData $entry): string => $entry->date, AgendaEntries::from($this->entries, '2026-11-25')))->toBe(['2026-11-25', '2026-11-26', '2026-11-27']);
});

test('the total of the amounts, to the cent, an entry without one counting nothing', function (): void {
    expect(AgendaEntries::total(AgendaEntries::withoutIncomes($this->entries)))->toBe(30.3)
        ->and(AgendaEntries::total([]))->toBe(0.0);
});
