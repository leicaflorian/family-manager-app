import { describe, expect, it } from 'vite-plus/test';
import { formatData, formatEuro } from './formato';

describe('formatEuro', () => {
    it("scrive gli importi all'italiana", () => {
        expect(formatEuro(84.2)).toBe('84,20 €');
        expect(formatEuro(1234.5)).toBe('1.234,50 €');
        expect(formatEuro(0)).toBe('0,00 €');
    });
});

describe('formatData', () => {
    it('scrive gg/mm/aaaa da una data del server', () => {
        expect(formatData('2026-10-15')).toBe('15/10/2026');
    });

    it("accetta anche una data con l'ora, senza cambiare giorno", () => {
        expect(formatData('2026-10-15T23:30:00+02:00')).toBe('15/10/2026');
    });

    it('accetta un Date', () => {
        expect(formatData(new Date(2026, 9, 5))).toBe('05/10/2026');
    });
});
