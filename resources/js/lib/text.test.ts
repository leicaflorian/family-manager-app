import { describe, expect, it } from 'vite-plus/test';
import { plainText, splitAround, splitTail } from './text';

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

describe('plainText', () => {
    it('takes the capitals and the accents away, to compare texts', () => {
        expect(plainText('Più Avanti')).toBe('piu avanti');
        expect(plainText('ÉCOLE')).toBe('ecole');
    });
});

describe('splitAround', () => {
    it('cuts a translated sentence around one of its placeholders, to draw that piece apart', () => {
        expect(
            splitAround('The filter :name already takes it.', 'name'),
        ).toEqual(['The filter ', ' already takes it.']);
        expect(
            splitAround(':count movements, see :undo now', 'undo', {
                count: '3',
            }),
        ).toEqual(['3 movements, see ', ' now']);
    });

    it('gives the whole text before when the placeholder is not there', () => {
        expect(splitAround('Nothing here', 'name')).toEqual([
            'Nothing here',
            '',
        ]);
    });
});
