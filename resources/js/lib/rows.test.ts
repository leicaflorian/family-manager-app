import { describe, expect, it } from 'vite-plus/test';
import { rowLink } from './rows';

describe('rowLink', () => {
    it('lights a row that leads somewhere with the soft blue, on hover and on focus', () => {
        expect(rowLink).toContain('cursor-pointer');
        expect(rowLink).toContain('hover:bg-action-soft');
        expect(rowLink).toContain('focus-visible:bg-action-soft');
    });
});
