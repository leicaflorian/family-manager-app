import { describe, expect, it } from 'vite-plus/test';
import {
    bankInitials,
    bankMoney,
    dayOf,
    lastReadText,
    readTimes,
    similarOf,
} from './banks';

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

describe('bankMoney', () => {
    it('writes money that came in with the plus, as the bank lists do', () => {
        expect(bankMoney(407.6, 'EUR')).toBe('+407,60 €');
        expect(bankMoney(0.04, 'EUR')).toBe('+0,04 €');
        expect(bankMoney(12, 'USD')).toBe('+12,00 USD');
    });

    it('leaves money that went out and zero as they are', () => {
        expect(bankMoney(-18.4, 'EUR')).toBe('-18,40 €');
        expect(bankMoney(0, 'EUR')).toBe('0,00 €');
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

describe('similarOf', () => {
    const flash = {
        count: 3,
        kind: 'expense',
        rows: [],
        action: 'create',
        filter: { id: null, name: 'Satispay', action: 'create' },
        reason: { kind: 'mandate', name: 'Satispay' },
        min: 10,
        max: 50,
        from: '2026-09-02',
        to: '2026-11-17',
        url: '/budget/bank/filters/create?from=2',
    };

    it('takes the flash `similar` the server sent', () => {
        expect(similarOf({ similar: flash })).toEqual(flash);
    });

    it('takes it without a reason, when the server has no name or words for it', () => {
        expect(similarOf({ similar: { ...flash, reason: null } })).toEqual({
            ...flash,
            reason: null,
        });
    });

    it('gives null without it, or when it has not the shape', () => {
        expect(similarOf(undefined)).toBeNull();
        expect(similarOf({})).toBeNull();
        expect(similarOf({ similar: { ...flash, count: 0 } })).toBeNull();
        expect(similarOf({ similar: { ...flash, action: 'x' } })).toBeNull();
        expect(similarOf({ similar: { ...flash, url: 5 } })).toBeNull();
    });

    it('gives null when the filter, the reason, the amounts or the days have not the shape', () => {
        for (const wrong of [
            { filter: undefined },
            { filter: null },
            { filter: 'Satispay' },
            { reason: undefined },
            { reason: { kind: 'other', name: 'Satispay' } },
            { reason: { kind: 'mandate', name: 5 } },
            { kind: 'loan' },
            { min: '10' },
            { max: null },
            { from: undefined },
            { to: 20261117 },
        ]) {
            expect(similarOf({ similar: { ...flash, ...wrong } })).toBeNull();
        }
    });
});
