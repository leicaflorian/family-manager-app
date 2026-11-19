import { describe, expect, it } from 'vite-plus/test';
import { linkKind, showMoreLabel } from './showMore';

describe('showMoreLabel', () => {
    it('says how many come with a tap and how many are left', () => {
        expect(showMoreLabel(19, 5)).toBe('Show 5 more · 19 left');
    });

    it('never promises more than are left', () => {
        expect(showMoreLabel(3, 5, 'documents')).toBe(
            'Show 3 more documents · 3 left',
        );
    });

    it('says when only one is left', () => {
        expect(showMoreLabel(1, 5)).toBe('Show 1 more · 1 left');
    });

    it('has its own text for the things that take «altri» in Italian', () => {
        expect(showMoreLabel(5, 3, 'masculine')).toBe('Show 3 others · 5 left');
        expect(showMoreLabel(1, 3, 'masculine')).toBe('Show 1 other · 1 left');
    });
});

describe('linkKind', () => {
    it('gives each type of the links the gender of its name in Italian', () => {
        expect(linkKind('vehicle')).toBe('masculine');
        expect(linkKind('financial_account')).toBe('masculine');
        expect(linkKind('subscription')).toBe('masculine');
        expect(linkKind('insurance_policy')).toBe('feminine');
        expect(linkKind('utility')).toBe('feminine');
        expect(linkKind('enrollment')).toBe('feminine');
    });
});
