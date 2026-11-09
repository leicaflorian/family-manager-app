import { describe, expect, it } from 'vite-plus/test';
import { useFormat } from './useFormat';

describe('useFormat', () => {
    it('gives the pages amounts and dates written as on the canvas', () => {
        const { euro, date } = useFormat();

        expect(euro(84.2)).toBe('84,20 €');
        expect(date('2026-10-15')).toBe('15/10/2026');
    });

    it('writes a day and time of Rome as the server sends them', () => {
        const { dateTime } = useFormat();

        expect(dateTime('2026-12-10 21:30')).toBe('10/12/2026 21:30');
        expect(dateTime(null)).toBe('');
    });

    it('leaves empty what is missing', () => {
        const { euro, date } = useFormat();

        expect(euro(null)).toBe('');
        expect(date(null)).toBe('');
    });
});
