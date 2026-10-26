import { describe, expect, it } from 'vite-plus/test';
import { elencoNomi } from './nomi';

describe('elencoNomi', () => {
    it("unisce i nomi con le virgole e la «e» prima dell'ultimo", () => {
        expect(elencoNomi(['Marco', 'Laura', 'Giulia'])).toBe(
            'Marco, Laura e Giulia',
        );
        expect(elencoNomi(['Marco', 'Laura'])).toBe('Marco e Laura');
        expect(elencoNomi(['Marco'])).toBe('Marco');
        expect(elencoNomi([])).toBe('');
    });
});
