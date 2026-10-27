import { describe, expect, it } from 'vite-plus/test';
import { useFormat } from './useFormat';

describe('useFormat', () => {
    it('gives the pages amounts and dates written as on the canvas', () => {
        const { euro, date } = useFormat();

        expect(euro(84.2)).toBe('84,20 €');
        expect(date('2026-10-15')).toBe('15/10/2026');
    });

    it('leaves empty what is missing', () => {
        const { euro, date } = useFormat();

        expect(euro(null)).toBe('');
        expect(date(null)).toBe('');
    });
});
