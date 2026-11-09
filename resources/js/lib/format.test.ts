import { describe, expect, it } from 'vite-plus/test';
import {
    formatDate,
    formatDateTime,
    formatRomeText,
    formatShortDateTime,
    formatEuro,
    formatLongDate,
    formatSize,
    isoDate,
    relativeDays,
    todayInRome,
} from './format';

describe('formatEuro', () => {
    it('writes amounts the Italian way', () => {
        expect(formatEuro(84.2)).toBe('84,20 €');
        expect(formatEuro(1234.5)).toBe('1.234,50 €');
        expect(formatEuro(0)).toBe('0,00 €');
    });
});

describe('formatDate', () => {
    it('writes dd/mm/yyyy from a date of the server', () => {
        expect(formatDate('2026-10-15')).toBe('15/10/2026');
    });

    it('accepts a date with a time too, without changing day', () => {
        expect(formatDate('2026-10-15T23:30:00+02:00')).toBe('15/10/2026');
    });

    it('accepts a Date', () => {
        expect(formatDate(new Date(2026, 9, 5))).toBe('05/10/2026');
    });
});

describe('isoDate', () => {
    it('writes the day of a date as the server does, in local time', () => {
        expect(isoDate(new Date(2026, 9, 27, 23, 30))).toBe('2026-10-27');
        expect(isoDate(new Date(2026, 0, 5))).toBe('2026-01-05');
    });
});

describe('formatLongDate', () => {
    it('writes the day with its name, in the language of the page', () => {
        expect(formatLongDate('2026-10-26', 'it')).toBe(
            'Lunedì 26 ottobre 2026',
        );
        expect(formatLongDate('2026-10-26', 'en')).toBe(
            'Monday 26 October 2026',
        );
    });
});

describe('relativeDays', () => {
    const today = new Date('2026-10-26T10:00:00+01:00');

    it('counts the days from today in Rome', () => {
        expect(relativeDays('2026-10-26', today)).toBe('today');
        expect(relativeDays('2026-10-27', today)).toBe('tomorrow');
        expect(relativeDays('2026-11-21', today)).toBe('in 26 days');
        expect(relativeDays('2026-10-25', today)).toBe('yesterday');
        expect(relativeDays('2026-10-23', today)).toBe('3 days ago');
    });

    it('reads late evening in Rome as the same day', () => {
        expect(
            relativeDays('2026-10-28', new Date('2026-10-28T23:30:00+01:00')),
        ).toBe('today');
    });
});

describe('formatSize', () => {
    it('writes bytes, kilobytes and megabytes with the comma', () => {
        expect(formatSize(800)).toBe('800 B');
        expect(formatSize(348_160)).toBe('340 KB');
        expect(formatSize(1_258_291)).toBe('1,2 MB');
        expect(formatSize(26_214_400)).toBe('25 MB');
    });
});

describe('todayInRome', () => {
    it('is the day on the calendar of Rome, whatever the clock of the computer', () => {
        expect(todayInRome(new Date('2026-11-02T10:00:00+01:00'))).toBe(
            '2026-11-02',
        );
        // 23:30 in London on the 1st is already the 2nd in Rome
        expect(todayInRome(new Date('2026-11-01T23:30:00Z'))).toBe(
            '2026-11-02',
        );
    });
});

describe('formatDateTime', () => {
    it('writes the day and the time in Rome, from a moment of the server', () => {
        expect(formatDateTime('2026-10-25T09:41:00+01:00')).toBe(
            '25/10/2026 09:41',
        );
        // 22:30 UTC on 31/10 is 23:30 in Rome
        expect(formatDateTime('2026-10-31T22:30:00Z')).toBe('31/10/2026 23:30');
    });
});

describe('formatShortDateTime', () => {
    it('writes the day without the year and the time, as the phone shows them', () => {
        expect(formatShortDateTime('2026-10-14T07:58:00+02:00')).toBe(
            '14/10 · 07:58',
        );
    });
});

describe('formatRomeText', () => {
    it('rewrites a day and time already in the time of Rome', () => {
        expect(formatRomeText('2026-12-10 21:30')).toBe('10/12/2026 21:30');
    });
});
