import { describe, expect, it } from 'vite-plus/test';
import { addDays, addMonths, addYears, endOfWeek } from './days';

describe('addDays', () => {
    it('moves forward across the end of a month and of a year', () => {
        expect(addDays('2026-10-31', 1)).toBe('2026-11-01');
        expect(addDays('2026-12-28', 7)).toBe('2027-01-04');
    });

    it('moves back with a negative number', () => {
        expect(addDays('2026-11-01', -1)).toBe('2026-10-31');
    });

    it('does not shift with the change to winter time', () => {
        expect(addDays('2026-10-24', 1)).toBe('2026-10-25');
        expect(addDays('2026-10-25', 1)).toBe('2026-10-26');
    });
});

describe('addMonths', () => {
    it('keeps the same day of the month', () => {
        expect(addMonths('2026-11-02', 1)).toBe('2026-12-02');
        expect(addMonths('2026-12-15', 1)).toBe('2027-01-15');
    });

    it('stops at the last day of a shorter month', () => {
        expect(addMonths('2026-01-31', 1)).toBe('2026-02-28');
        expect(addMonths('2026-10-31', 1)).toBe('2026-11-30');
    });
});

describe('addYears', () => {
    it('keeps the same day', () => {
        expect(addYears('2026-10-20', 1)).toBe('2027-10-20');
        expect(addYears('2026-10-20', 5)).toBe('2031-10-20');
    });

    it('turns the 29th of February into the 28th', () => {
        expect(addYears('2028-02-29', 1)).toBe('2029-02-28');
    });
});

describe('endOfWeek', () => {
    it('gives the Sunday that ends the week, which starts on Monday', () => {
        expect(endOfWeek('2026-11-02')).toBe('2026-11-08');
        expect(endOfWeek('2026-11-05')).toBe('2026-11-08');
        expect(endOfWeek('2026-12-30')).toBe('2027-01-03');
    });

    it('gives the day itself on a Sunday', () => {
        expect(endOfWeek('2026-11-08')).toBe('2026-11-08');
    });
});
