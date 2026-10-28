import { describe, expect, it } from 'vite-plus/test';
import { areaFill, areaIcon, areaName, areaOf } from './areas';

describe('areaOf', () => {
    it('recognises an area and discards the rest', () => {
        expect(areaOf('home')).toBe('home');
        expect(areaOf('purple')).toBeUndefined();
        expect(areaOf(null)).toBeUndefined();
    });
});

describe('areaName', () => {
    it('names every area', () => {
        expect(areaName('home')).toBe('Home');
        expect(areaName('banks')).toBe('Banks and loans');
        expect(areaName('paperwork')).toBe('Paperwork');
    });
});

describe('areaIcon', () => {
    it('gives an icon to every area', () => {
        for (const area of Object.keys(areaFill)) {
            expect(areaIcon(area)).toBeDefined();
        }
        expect(areaIcon('unknown')).toBe(areaIcon('general'));
    });
});
