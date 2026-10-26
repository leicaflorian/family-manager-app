import { describe, expect, it } from 'vite-plus/test';
import { useFormato } from './useFormato';

describe('useFormato', () => {
    it('dà alle pagine importi e date scritti come nella tela', () => {
        const { euro, data } = useFormato();

        expect(euro(84.2)).toBe('84,20 €');
        expect(data('2026-10-15')).toBe('15/10/2026');
    });

    it('lascia vuoto quello che manca', () => {
        const { euro, data } = useFormato();

        expect(euro(null)).toBe('');
        expect(data(null)).toBe('');
    });
});
