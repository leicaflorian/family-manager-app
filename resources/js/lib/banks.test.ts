import { describe, expect, it } from 'vite-plus/test';
import { bankInitials, lastReadText } from './banks';

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
