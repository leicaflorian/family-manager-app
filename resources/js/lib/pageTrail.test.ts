import { describe, expect, it } from 'vite-plus/test';
import { createTrailState, isSectionPage } from './pageTrail';

describe('createTrailState', () => {
    it('sets and clears the pieces', () => {
        const state = createTrailState();
        expect(state.pieces.value).toEqual([]);

        state.set([{ label: 'Areas', href: '/areas' }, { label: 'Home' }]);
        expect(state.pieces.value).toHaveLength(2);

        state.clear();
        expect(state.pieces.value).toEqual([]);
    });
});

describe('isSectionPage', () => {
    it('is true for the pages of a section', () => {
        expect(isSectionPage('Home')).toBe(true);
        expect(isSectionPage('family/Organizations')).toBe(true);
    });

    it('is false for a page inside a section', () => {
        expect(isSectionPage('cards/Card')).toBe(false);
    });
});
