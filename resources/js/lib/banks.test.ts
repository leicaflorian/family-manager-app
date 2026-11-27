import { describe, expect, it } from 'vite-plus/test';
import { bankInitials, dayOf, lastReadText, readTimes } from './banks';

describe('bankInitials', () => {
    it('takes the first letter of the first two words', () => {
        expect(bankInitials('Buddy Bank')).toBe('BB');
        expect(bankInitials('Intesa Sanpaolo Private')).toBe('IS');
    });

    it('splits a name written in one word at its capitals', () => {
        expect(bankInitials('BancoPosta')).toBe('BP');
    });

    it('takes the first two letters of a single word', () => {
        expect(bankInitials('Postepay')).toBe('PO');
        expect(bankInitials('N26')).toBe('N2');
    });

    it('gives nothing for an empty name', () => {
        expect(bankInitials('  ')).toBe('');
    });
});

describe('lastReadText', () => {
    const now = new Date('2026-11-20T14:30:00Z');

    it('says today and the time for a read of today', () => {
        expect(lastReadText('2026-11-20T13:00', now)).toBe(
            'Last update today at 13:00',
        );
    });

    it('says the day and the time for an older read', () => {
        expect(lastReadText('2026-11-18T07:00', now)).toBe(
            'Last update on 18/11/2026 at 07:00',
        );
    });
});

describe('readTimes', () => {
    it('writes every hour with its own «at», as the boards do', () => {
        expect(readTimes([7, 13, 19])).toBe('at 7, at 13, and at 19');
        expect(readTimes([6, 18])).toBe('at 6 and at 18');
        expect(readTimes([7])).toBe('at 7');
    });
});

describe('dayOf', () => {
    const now = new Date('2026-11-20T14:30:00Z');

    it('writes the day and month for a day of this year', () => {
        expect(dayOf({ booked_on: '2026-11-06', value_on: null }, now)).toBe(
            '06/11',
        );
    });

    it('writes the year for a day of another year', () => {
        expect(dayOf({ booked_on: '2025-12-30', value_on: null }, now)).toBe(
            '30/12/2025',
        );
    });

    it('takes the day the bank gave for a transaction not booked yet', () => {
        expect(dayOf({ booked_on: null, value_on: '2026-11-05' }, now)).toBe(
            '05/11',
        );
    });

    it('is empty without any day', () => {
        expect(dayOf({ booked_on: null, value_on: null }, now)).toBe('');
    });
});
