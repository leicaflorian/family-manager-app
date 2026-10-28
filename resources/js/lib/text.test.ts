import { describe, expect, it } from 'vite-plus/test';
import { splitTail } from './text';

describe('splitTail', () => {
    it('keeps the last characters apart, to show them after the ellipsis', () => {
        expect(splitTail('Polizza RC auto 2026', 8)).toEqual([
            'Polizza RC a',
            'uto 2026',
        ]);
    });

    it('leaves a short text whole', () => {
        expect(splitTail('Bollo', 8)).toEqual(['Bollo', '']);
    });

    it('does not break a character made of two halves', () => {
        expect(splitTail('Viaggio 🏖️ mare', 5)).toEqual([
            'Viaggio 🏖️',
            ' mare',
        ]);
        expect(splitTail('ab😀cdef', 5)).toEqual(['ab', '😀cdef']);
    });
});
