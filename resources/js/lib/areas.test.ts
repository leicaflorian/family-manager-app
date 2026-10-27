import { describe, expect, it } from 'vite-plus/test';
import { areaOf } from './areas';

describe('areaOf', () => {
    it('recognises an area and discards the rest', () => {
        expect(areaOf('home')).toBe('home');
        expect(areaOf('purple')).toBeUndefined();
        expect(areaOf(null)).toBeUndefined();
    });
});
