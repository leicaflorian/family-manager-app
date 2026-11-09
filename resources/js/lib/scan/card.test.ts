import { describe, expect, it } from 'vite-plus/test';
import { findCard, layout, millimetreOf, sizeOf } from './card';
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
