import { describe, expect, it } from 'vite-plus/test';
import { formatDate, formatEuro, formatLongDate, isoDate } from './format';

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
