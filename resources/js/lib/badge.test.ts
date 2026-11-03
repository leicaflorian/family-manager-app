import { describe, expect, it } from 'vite-plus/test';
import { countBadge } from './badge';

describe('countBadge', () => {
    it('writes the number, and 99+ above 99', () => {
        expect(countBadge(4)).toBe('4');
        expect(countBadge(99)).toBe('99');
        expect(countBadge(120)).toBe('99+');
    });
});
