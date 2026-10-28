import { describe, expect, it } from 'vite-plus/test';
import { areaSummary, cardCountText, overdueText } from './cardTexts';

describe('cardTexts', () => {
    it('counts the cards', () => {
        expect(cardCountText(0)).toBe('No cards');
        expect(cardCountText(1)).toBe('1 card');
        expect(cardCountText(3)).toBe('3 cards');
    });

    it('counts the overdue ones', () => {
        expect(overdueText(1)).toBe('1 overdue');
        expect(overdueText(4)).toBe('4 overdue');
    });

    it('sums up an area, with the overdue ones only if there are', () => {
        expect(areaSummary(2, 1)).toBe('2 cards · 1 overdue');
        expect(areaSummary(2, 0)).toBe('2 cards');
    });
});
