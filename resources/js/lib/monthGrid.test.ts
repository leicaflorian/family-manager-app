import { describe, expect, it } from 'vite-plus/test';
import { agendaEntries } from '@/test/fixtures';
import { monthDays } from './monthGrid';

/** An entry of the Agenda with the fields the tests change. */
function entry(
    change: Partial<App.Data.AgendaEntryData>,
): App.Data.AgendaEntryData {
    return { ...agendaEntries[0], ...change };
}

describe('monthDays', () => {
    it('gives every day of the period, also the empty ones', () => {
        const days = monthDays(
            '2026-11-01',
            '2026-11-03',
            [],
            [],
            '2026-11-02',
        );

        expect(days.map((day) => day.date)).toEqual([
            '2026-11-01',
            '2026-11-02',
            '2026-11-03',
        ]);
        expect(days.every((day) => day.items.length === 0)).toBe(true);
    });

    it('puts the done entries at the end of the day', () => {
        const open = [entry({ key: 'a', date: '2026-11-02', title: 'Open' })];
        const done = [entry({ key: 'b', date: '2026-11-02', title: 'Done' })];

        const [day] = monthDays(
            '2026-11-02',
            '2026-11-02',
            open,
            done,
            '2026-11-02',
        );

        expect(day.items.map((item) => [item.title, item.done])).toEqual([
            ['Open', false],
            ['Done', true],
        ]);
    });

    it('marks as late only an open entry before today', () => {
        const open = [
            entry({ key: 'a', date: '2026-11-01' }),
            entry({ key: 'b', date: '2026-11-02' }),
        ];
        const done = [entry({ key: 'c', date: '2026-11-01' })];

        const days = monthDays(
            '2026-11-01',
            '2026-11-02',
            open,
            done,
            '2026-11-02',
        );

        expect(days[0].items.map((item) => item.late)).toEqual([true, false]);
        expect(days[1].items.map((item) => item.late)).toEqual([false]);
    });

    it('ignores the entries out of the period', () => {
        const open = [entry({ key: 'a', date: '2026-12-01' })];

        const days = monthDays(
            '2026-11-01',
            '2026-11-02',
            open,
            [],
            '2026-11-02',
        );

        expect(days.flatMap((day) => day.items)).toEqual([]);
    });
});
