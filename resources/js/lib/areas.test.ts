import { describe, expect, it } from 'vite-plus/test';
import { areaFill, areaIcon, areaOf, colorFill, colorTile } from './areas';

describe('areaOf', () => {
    it('recognises an area and discards the rest', () => {
        expect(areaOf('home')).toBe('home');
        expect(areaOf('purple')).toBeUndefined();
        expect(areaOf(null)).toBeUndefined();
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

describe('colorTile and colorFill', () => {
    it('colour inline with a colour of its own', () => {
        expect(colorTile('#0797B9')).toEqual({
            color: '#0797B9',
            backgroundColor: '#0797B91f',
        });
        expect(colorFill('#0797B9')).toEqual({ backgroundColor: '#0797B9' });
    });

    it('leave an area or no colour to the classes', () => {
        expect(colorTile('home')).toEqual({});
        expect(colorTile(null)).toEqual({});
        expect(colorFill('home')).toEqual({});
        expect(colorFill(null)).toEqual({});
    });
});
