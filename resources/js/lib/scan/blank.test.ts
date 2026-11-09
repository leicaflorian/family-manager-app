import { describe, expect, it } from 'vite-plus/test';
import { inkShareOf, isBlankPixels } from './blank';
import type { Pixels } from './crop';

/** A side of 200 x 280 dots of `paper`, with `lines` rows of dark ink 2 dots tall. */
function side(paper: number, lines: number, ink = 20): Pixels {
    const width = 200;
    const height = 280;
    const data = new Uint8ClampedArray(width * height * 4).fill(255);
    for (let i = 0; i < width * height; i++) {
        data[i * 4] = data[i * 4 + 1] = data[i * 4 + 2] = paper;
    }
    for (let line = 0; line < lines; line++) {
        for (let y = 100 + line * 6; y < 102 + line * 6; y++) {
            for (let x = 30; x < 170; x++) {
                const at = (y * width + x) * 4;
                data[at] = data[at + 1] = data[at + 2] = ink;
            }
        }
    }
    return { width, height, data };
}

describe('isBlankPixels', () => {
    it('a white side is blank', () => {
        expect(isBlankPixels(side(250, 0))).toBe(true);
    });

    it('a grey paper with writing showing through is blank', () => {
        expect(isBlankPixels(side(200, 0))).toBe(true);
        expect(inkShareOf(side(240, 3, 225))).toBe(0);
    });

    it('a side with a few lines is kept', () => {
        expect(isBlankPixels(side(250, 3))).toBe(false);
    });

    it('leaves out the edges: a dark band at the border is not ink', () => {
        const picture = side(250, 0);
        for (let y = 0; y < 5; y++) {
            for (let x = 0; x < picture.width; x++)
                picture.data[(y * picture.width + x) * 4] = 0;
        }
        expect(isBlankPixels(picture)).toBe(true);
    });
});
