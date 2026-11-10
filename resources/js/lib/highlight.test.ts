import { describe, expect, it } from 'vite-plus/test';
import { highlightParts } from './highlight';

describe('highlightParts', () => {
    it('marks the part of the text that matches, keeping the original text', () => {
        expect(highlightParts('Fiat Panda', 'pan')).toEqual([
            { text: 'Fiat ', match: false },
            { text: 'Pan', match: true },
            { text: 'da', match: false },
        ]);
    });

    it('ignores accents and case', () => {
        expect(highlightParts('Perché', 'perche')).toEqual([
            { text: 'Perché', match: true },
        ]);
    });

    it('marks every word of the query', () => {
        const parts = highlightParts('RC auto Panda', 'auto pan');

        expect(parts.filter((part) => part.match).map((p) => p.text)).toEqual([
            'auto',
            'Pan',
        ]);
        expect(parts.map((part) => part.text).join('')).toBe('RC auto Panda');
    });

    it('does not mark anything for a one letter query', () => {
        expect(highlightParts('Fiat Panda', 'a')).toEqual([
            { text: 'Fiat Panda', match: false },
        ]);
    });

    it('copes with letters that decompose into more characters', () => {
        expect(highlightParts('Caffè e Perché', 'perche')).toEqual([
            { text: 'Caffè e ', match: false },
            { text: 'Perché', match: true },
        ]);
    });
});
