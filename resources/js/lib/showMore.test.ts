import { describe, expect, it } from 'vite-plus/test';
import { showMoreLabel } from './showMore';

describe('showMoreLabel', () => {
    it('says how many come with a tap and how many are left', () => {
        expect(showMoreLabel(19, 5)).toBe('Show 5 more · 19 left');
    });

    it('never promises more than are left', () => {
        expect(showMoreLabel(3, 5, true)).toBe(
            'Show 3 more documents · 3 left',
        );
    });

    it('says when only one is left', () => {
        expect(showMoreLabel(1, 5)).toBe('Show 1 more · 1 left');
    });
});
