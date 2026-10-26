import { describe, expect, it } from 'vite-plus/test';
import { errori } from './errori';

describe('errori', () => {
    it("un messaggio del server diventa l'elenco che vuole FormKit", () => {
        expect(errori('Il campo email è richiesto.')).toEqual([
            'Il campo email è richiesto.',
        ]);
    });

    it("senza messaggio l'elenco è vuoto", () => {
        expect(errori(undefined)).toEqual([]);
    });
});
