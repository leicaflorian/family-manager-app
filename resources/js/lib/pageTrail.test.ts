import { describe, expect, it } from 'vite-plus/test';
import {
    createTrailState,
    hasTitleTools,
    isSectionPage,
    SECTION_PAGES,
} from './pageTrail';

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

    it('is false for the pages under Budget that have no tab', () => {
        expect(isSectionPage('budget/Comparison')).toBe(false);
        expect(isSectionPage('budget/Consumption')).toBe(false);
    });

    it('is false for From the bank, which shows its trail under Budget', () => {
        expect(isSectionPage('budget/BankTransactions')).toBe(false);
    });
});

describe('hasTitleTools', () => {
    it('is true for the section pages and the pages under a section in the sidebar', () => {
        expect(hasTitleTools('Home')).toBe(true);
        expect(hasTitleTools('budget/BankTransactions')).toBe(true);
    });

    it('is false for an inner page', () => {
        expect(hasTitleTools('cards/Card')).toBe(false);
        expect(hasTitleTools('budget/BankFilter')).toBe(false);
    });
});

describe('SECTION_PAGES', () => {
    it('comes from the sections of the navigation', () => {
        expect([...SECTION_PAGES].sort()).toEqual(
            [
                'Home',
                'Agenda',
                'Areas',
                'documents/Documents',
                'documents/ToSort',
                'mail/ReceivedMails',
                'budget/Movements',
                'budget/Planned',
                'family/People',
                'family/Organizations',
                'settings/Settings',
            ].sort(),
        );
    });
});
