import { describe, expect, it } from 'vite-plus/test';
import { areaDi } from './aree';

describe('areaDi', () => {
    it("riconosce un'area e scarta il resto", () => {
        expect(areaDi('casa')).toBe('casa');
        expect(areaDi('viola')).toBeUndefined();
        expect(areaDi(null)).toBeUndefined();
    });
});
