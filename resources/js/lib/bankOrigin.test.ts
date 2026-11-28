import { describe, expect, it } from 'vite-plus/test';
import { originWords } from './bankOrigin';

describe('originWords', () => {
    it('says a proposal the person accepted', () => {
        expect(originWords('proposed', null)).toBe('Proposal accepted');
        expect(originWords('proposed', null, true)).toBe('proposal accepted');
    });

    it('names the filter for a rule that came from one, otherwise says a rule', () => {
        expect(originWords('rule', 'Luce')).toBe('From the filter Luce');
        expect(originWords('rule', null)).toBe('From a rule');
        expect(originWords('rule', null, true)).toBe('from a rule');
    });

    it('says the other origins', () => {
        expect(originWords('assigned', null)).toBe('Chosen by hand');
        expect(originWords('created', null)).toBe('Created from here');
        expect(originWords('duplicate', null)).toBe(
            'Already recorded, now linked',
        );
    });
});
