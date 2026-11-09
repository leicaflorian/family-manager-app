import { describe, expect, it } from 'vite-plus/test';
import {
    densityOf,
    findCard,
    improveCanvas,
    layout,
    millimetreOf,
    sizeOf,
} from './card';
import { improveSide } from './improve';
import type { Pixels } from './crop';

/** A scanner side with a lid of 235 grey and a card of `box`, drawn dark with a lighter print. */
function glass(
    width: number,
    height: number,
    box: { x: number; y: number; width: number; height: number } | null,
): Pixels {
    const data = new Uint8ClampedArray(width * height * 4);
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const inside =
                box &&
                x >= box.x &&
                x < box.x + box.width &&
                y >= box.y &&
                y < box.y + box.height;
            const value = inside ? 90 : 235;
            const at = (y * width + x) * 4;
            data[at] = data[at + 1] = data[at + 2] = value;
            data[at + 3] = 255;
        }
    }
    return { width, height, data };
}

describe('findCard', () => {
    // 50 dots per 10 mm: a side of 1050 x 1485 is an A4 at 5 dots a millimetre
    const MM = 5;

    it('finds a card put straight on the glass', () => {
        const box = { x: 200, y: 300, width: 428, height: 270 };
        const found = findCard(glass(1050, 1485, box), MM);
        expect(found).not.toBeNull();
        expect(found!.small).toBe(true);
        expect(found!.cut.angle).toBe(0);
        expect(Math.abs(found!.cut.box.x - box.x)).toBeLessThan(3);
        expect(Math.abs(found!.cut.box.y - box.y)).toBeLessThan(3);
        expect(Math.abs(found!.cut.box.width - box.width)).toBeLessThan(5);
        expect(Math.abs(found!.cut.box.height - box.height)).toBeLessThan(5);
    });

    it('finds nothing on an empty side', () => {
        expect(findCard(glass(1050, 1485, null), MM)).toBeNull();
    });

    it('does not take a whole page for a card', () => {
        const found = findCard(
            glass(1050, 1485, { x: 60, y: 60, width: 930, height: 1350 }),
            MM,
        );
        expect(found!.small).toBe(false);
    });
});

describe('layout and sizes', () => {
    it('puts two cards in a column, centred, with the same gaps', () => {
        const { scale, places } = layout([
            { width: 85.6, height: 54 },
            { width: 85.6, height: 54 },
        ]);
        expect(scale).toBe(1);
        expect(places[0].x).toBeCloseTo((210 - 85.6) / 2);
        expect(places[1].y - (places[0].y + 54)).toBeCloseTo(places[0].y);
    });

    it('reduces cards that do not fit', () => {
        expect(layout([{ width: 300, height: 100 }]).scale).toBeLessThan(1);
    });

    it('turns the size of a card with its quarters', () => {
        const card = {
            cut: { box: { x: 0, y: 0, width: 500, height: 300 }, angle: 0 },
            millimetre: 5,
        };
        expect(sizeOf({ ...card, turns: 0 })).toEqual({
            width: 100,
            height: 60,
        });
        expect(sizeOf({ ...card, turns: 1 })).toEqual({
            width: 60,
            height: 100,
        });
    });

    it('takes the dots of a millimetre from the stated density, then the asked one, then the width', () => {
        expect(
            millimetreOf({ width: 1000, height: 1400 }, 300, 150),
        ).toBeCloseTo(300 / 25.4);
        expect(
            millimetreOf({ width: 1000, height: 1400 }, 72, 150),
        ).toBeCloseTo(150 / 25.4);
        expect(millimetreOf({ width: 2100, height: 2970 }, null, null)).toBe(
            10,
        );
    });
});

describe('densityOf', () => {
    const jfif = (unit: number, across: number, down: number) =>
        new Uint8Array([
            0xff,
            0xd8,
            0xff,
            0xe0,
            0x00,
            0x10,
            0x4a,
            0x46,
            0x49,
            0x46,
            0x00,
            0x01,
            0x01,
            unit,
            across >> 8,
            across & 255,
            down >> 8,
            down & 255,
            0x00,
            0x00,
            0xff,
            0xda,
            0x00,
            0x02,
        ]);

    it('reads dots per inch from a JFIF header', async () => {
        expect(await densityOf(new Blob([jfif(1, 300, 300)]))).toBe(300);
    });

    it('reads dots per centimetre and refuses two different densities', async () => {
        expect(await densityOf(new Blob([jfif(2, 118, 118)]))).toBe(300);
        expect(await densityOf(new Blob([jfif(1, 300, 200)]))).toBeNull();
        expect(await densityOf(new Blob([jfif(0, 1, 1)]))).toBeNull();
    });

    it('reads the density of an Exif header, little endian', async () => {
        // TIFF: header (8), one IFD of 3 entries (XResolution, YResolution, ResolutionUnit), then 300/1 twice
        const tiff: number[] = [0x49, 0x49, 0x2a, 0x00, 8, 0, 0, 0, 3, 0];
        const entry = (tag: number, type: number, value: number) => [
            tag & 255,
            tag >> 8,
            type,
            0,
            1,
            0,
            0,
            0,
            value & 255,
            value >> 8,
            0,
            0,
        ];
        const at = 8 + 2 + 36 + 4;
        tiff.push(
            ...entry(0x011a, 5, at),
            ...entry(0x011b, 5, at + 8),
            ...entry(0x0128, 3, 2),
        );
        tiff.push(0, 0, 0, 0);
        tiff.push(0x2c, 1, 0, 0, 1, 0, 0, 0, 0x2c, 1, 0, 0, 1, 0, 0, 0);
        const body = [0x45, 0x78, 0x69, 0x66, 0, 0, ...tiff];
        const length = body.length + 2;
        const bytes = new Uint8Array([
            0xff,
            0xd8,
            0xff,
            0xe1,
            length >> 8,
            length & 255,
            ...body,
            0xff,
            0xda,
            0,
            2,
        ]);
        expect(await densityOf(new Blob([bytes]))).toBe(300);
    });

    it('gives null for something that is not a JPEG', async () => {
        expect(
            await densityOf(new Blob([new Uint8Array([1, 2, 3, 4])])),
        ).toBeNull();
    });
});

describe('improveCanvas', () => {
    /** A canvas of two dots that keeps what is drawn on it: jsdom has no canvas of its own. */
    function fakeCanvas(): HTMLCanvasElement & { drawn: Uint8ClampedArray } {
        const state = {
            width: 2,
            height: 1,
            drawn: new Uint8ClampedArray([200, 30, 90, 255, 10, 220, 40, 255]),
        };
        const context = {
            getImageData: (
                _x: number,
                _y: number,
                width: number,
                height: number,
            ) => ({
                width,
                height,
                data: new Uint8ClampedArray(state.drawn),
            }),
            createImageData: (width: number, height: number) => ({
                width,
                height,
                data: new Uint8ClampedArray(width * height * 4),
            }),
            putImageData: (image: { data: Uint8ClampedArray }) => {
                state.drawn = new Uint8ClampedArray(image.data);
            },
        };
        return Object.assign(state, {
            getContext: () => context,
        }) as unknown as HTMLCanvasElement & { drawn: Uint8ClampedArray };
    }

    it('draws on the page what Improve asks, as for a side', () => {
        const canvas = fakeCanvas();
        const before = {
            width: 2,
            height: 1,
            data: new Uint8ClampedArray(canvas.drawn),
        };

        improveCanvas(canvas, { angle: 0, enhance: 0, grey: true });

        expect(Array.from(canvas.drawn)).toEqual(
            Array.from(
                improveSide(before, { angle: 0, enhance: 0, grey: true }).data,
            ),
        );
    });
});
