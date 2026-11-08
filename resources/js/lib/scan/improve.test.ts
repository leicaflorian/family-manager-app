import { describe, expect, it } from 'vite-plus/test';
import type { Pixels } from './crop';
import {
    IMPROVE_DEFAULTS,
    enhance,
    grey,
    improve,
    skewOf,
    turnBy,
} from './improve';

/** A page of text-like bars lying at `degrees` clockwise (y grows downwards), dark ink on grey paper. */
function textPage(degrees: number, width = 600, height = 800): Pixels {
    const data = new Uint8ClampedArray(width * height * 4);
    const angle = (degrees * Math.PI) / 180;
    const cos = Math.cos(angle);
    const sin = Math.sin(angle);
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const across = (y - height / 2) * cos - (x - width / 2) * sin;
            const along = (x - width / 2) * cos + (y - height / 2) * sin;
            const bar = ((across % 14) + 14) % 14 < 4;
            const word = ((along % 40) + 40) % 40 < 32;
            const value =
                bar && word && Math.abs(along) < 230 && Math.abs(across) < 330
                    ? 30
                    : 220;
            const i = (y * width + x) * 4;
            data[i] = data[i + 1] = data[i + 2] = value;
            data[i + 3] = 255;
        }
    }
    return { width, height, data };
}

describe('skewOf', () => {
    it.each([0, 0.4, 1.3, -2.7, 4.5, -6.1, 7.4])(
        'finds %s degrees within a tenth',
        (degrees) => {
            const found = skewOf(textPage(degrees));
            expect(found).not.toBeNull();
            expect(Math.abs((found as number) - degrees)).toBeLessThanOrEqual(
                0.1001,
            );
        },
    );

    it('gives null for a blank page', () => {
        const data = new Uint8ClampedArray(200 * 200 * 4).fill(230);
        expect(skewOf({ width: 200, height: 200, data })).toBeNull();
    });
});

describe('turnBy', () => {
    it('straightens a crooked page', () => {
        const crooked = textPage(3);
        const straight = turnBy(crooked, -(skewOf(crooked) as number));
        expect(Math.abs(skewOf(straight) as number)).toBeLessThanOrEqual(0.2);
    });
});

/** Paper in a shadow that deepens to the left, with a row of text on it. */
function shadowed(): Pixels {
    const width = 400;
    const height = 300;
    const data = new Uint8ClampedArray(width * height * 4);
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const shade = 0.62 + 0.38 * (x / (width - 1));
            const ink = y % 40 < 4 && y > 20 && x > 30 && x < 370;
            const value = (ink ? 40 : 235) * shade;
            const i = (y * width + x) * 4;
            data[i] = data[i + 1] = data[i + 2] = value;
            data[i + 3] = 255;
        }
    }
    return { width, height, data };
}

describe('enhance', () => {
    it('brings the paper above 240 everywhere and keeps the text under 80', () => {
        const source = shadowed();
        const out = enhance(source);
        for (let y = 0; y < source.height; y++) {
            for (let x = 0; x < source.width; x++) {
                const i = (y * source.width + x) * 4;
                const ink = y % 40 < 4 && y > 20 && x > 30 && x < 370;
                if (ink && y % 40 > 0 && y % 40 < 3)
                    expect(out.data[i]).toBeLessThan(80);
                else if (
                    !ink &&
                    y % 40 > 6 &&
                    x > 8 &&
                    x < 392 &&
                    y > 8 &&
                    y < 292
                ) {
                    expect(out.data[i]).toBeGreaterThan(240);
                }
            }
        }
    });

    it('leaves the picture alone with strength 0', () => {
        const source = shadowed();
        expect(Array.from(enhance(source, 0).data)).toEqual(
            Array.from(source.data),
        );
    });
});

describe('grey', () => {
    it('makes red, green and blue equal', () => {
        const data = new Uint8ClampedArray([
            200, 30, 90, 255, 10, 220, 40, 255,
        ]);
        const out = grey({ width: 2, height: 1, data });
        for (let i = 0; i < 8; i += 4) {
            expect(out.data[i]).toBe(out.data[i + 1]);
            expect(out.data[i]).toBe(out.data[i + 2]);
            expect(out.data[i + 3]).toBe(255);
        }
    });
});

describe('improve', () => {
    it('has the defaults of the owner', () => {
        expect(IMPROVE_DEFAULTS).toEqual({
            enhance: true,
            grey: true,
            dewarp: false,
        });
    });

    it('does nothing when nothing is asked', () => {
        const source = shadowed();
        expect(improve(source, { enhance: false, grey: false })).toBe(source);
    });

    it('enhances then greys', () => {
        const out = improve(shadowed(), { enhance: true, grey: true });
        expect(out.data[0]).toBe(out.data[1]);
        expect(out.data[1]).toBe(out.data[2]);
    });
});
