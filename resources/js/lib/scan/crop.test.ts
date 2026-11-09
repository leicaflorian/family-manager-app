import { describe, expect, it } from 'vite-plus/test';
import {
    LONG_SIDE,
    SOURCE_LONG_SIDE,
    WHOLE,
    isSheet,
    shapeOf,
    sheetLayout,
    sideSize,
    sourceCorners,
    turn,
    turnCorners,
    warp,
    type Corners,
    type Pixels,
} from './crop';

/** A picture whose every pixel is different, so a wrong move shows. */
function gradient(width: number, height: number): Pixels {
    const data = new Uint8ClampedArray(width * height * 4);
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const i = (y * width + x) * 4;
            data[i] = (x * 255) / Math.max(1, width - 1);
            data[i + 1] = (y * 255) / Math.max(1, height - 1);
            data[i + 2] = (x * 7 + y * 13) % 256;
            data[i + 3] = 255;
        }
    }
    return { width, height, data };
}

const tilted: Corners = [
    { x: 0.2, y: 0.15 },
    { x: 0.8, y: 0.25 },
    { x: 0.75, y: 0.85 },
    { x: 0.15, y: 0.7 },
];

describe('constants', () => {
    it('has the sizes of the analysis', () => {
        expect(LONG_SIDE).toBe(2000);
        expect(SOURCE_LONG_SIDE).toBe(3000);
        expect(WHOLE).toEqual([
            { x: 0, y: 0 },
            { x: 1, y: 0 },
            { x: 1, y: 1 },
            { x: 0, y: 1 },
        ]);
    });
});

describe('turn', () => {
    it('swaps the sides with a quarter of a turn', () => {
        const turned = turn(gradient(6, 4), 1);
        expect([turned.width, turned.height]).toEqual([4, 6]);
        expect([
            turn(gradient(6, 4), 2).width,
            turn(gradient(6, 4), 2).height,
        ]).toEqual([6, 4]);
        expect(turn(gradient(6, 4), 3).width).toBe(4);
    });

    it('gives the photo back after four turns', () => {
        const photo = gradient(6, 4);
        let image = photo;
        for (let i = 0; i < 4; i++) image = turn(image, 1);
        expect(image.width).toBe(6);
        expect(Array.from(image.data)).toEqual(Array.from(photo.data));
    });

    it('turns clockwise: the top left pixel goes to the top right', () => {
        const photo = gradient(6, 4);
        const turned = turn(photo, 1);
        const at = (turned.width - 1) * 4;
        expect(Array.from(turned.data.slice(at, at + 4))).toEqual(
            Array.from(photo.data.slice(0, 4)),
        );
    });

    it('does not touch the original', () => {
        const photo = gradient(6, 4);
        const copy = Array.from(photo.data);
        turn(photo, 3);
        expect(Array.from(photo.data)).toEqual(copy);
    });
});

describe('turnCorners', () => {
    it('moves a point with (x, y) -> (1 - y, x) and renames the corners', () => {
        const turned = turnCorners(tilted, 1);
        // the old bottom left corner is now the top left one
        expect(turned[0].x).toBeCloseTo(1 - tilted[3].y);
        expect(turned[0].y).toBeCloseTo(tilted[3].x);
        expect(turned[1].x).toBeCloseTo(1 - tilted[0].y);
        expect(turned[1].y).toBeCloseTo(tilted[0].x);
    });

    it('brings the corners back after four turns', () => {
        const back = turnCorners(
            turnCorners(turnCorners(turnCorners(tilted, 1), 1), 1),
            1,
        );
        back.forEach((corner, i) => {
            expect(corner.x).toBeCloseTo(tilted[i].x);
            expect(corner.y).toBeCloseTo(tilted[i].y);
        });
        const four = turnCorners(tilted, 0);
        expect(four).toEqual(tilted);
    });

    it('leaves WHOLE as it is', () => {
        for (const turns of [0, 1, 2, 3] as const) {
            const turned = turnCorners(WHOLE, turns);
            turned.forEach((corner, i) => {
                expect(corner.x).toBeCloseTo(WHOLE[i].x);
                expect(corner.y).toBeCloseTo(WHOLE[i].y);
            });
        }
    });
});

describe('sourceCorners', () => {
    it('puts the points of turnCorners back where they were, under the names they got by turning', () => {
        for (const turns of [0, 1, 2, 3] as const) {
            const back = sourceCorners(turnCorners(tilted, turns), turns);
            // turnCorners renames the corners, so the first of the result is the corner `turns` places before it
            back.forEach((corner, i) => {
                const was = tilted[(i - turns + 4) % 4];
                expect(corner.x).toBeCloseTo(was.x);
                expect(corner.y).toBeCloseTo(was.y);
            });
        }
    });

    it('cuts the same sheet from the photo as taken as from the turned photo', () => {
        const photo = gradient(48, 32);
        for (const turns of [1, 2, 3] as const) {
            const onScreen = turnCorners(tilted, turns);
            const fromTurned = warp(turn(photo, turns), onScreen, 40);
            const fromTaken = warp(photo, sourceCorners(onScreen, turns), 40);
            expect([fromTaken.width, fromTaken.height]).toEqual([
                fromTurned.width,
                fromTurned.height,
            ]);
            expect(Array.from(fromTaken.data)).toEqual(
                Array.from(fromTurned.data),
            );
        }
    });
});

describe('warp', () => {
    it('gives back the photo for the whole photo', () => {
        const photo = gradient(20, 10);
        const cut = warp(photo, WHOLE, 2000);
        expect([cut.width, cut.height]).toEqual([20, 10]);
        expect(Array.from(cut.data)).toEqual(Array.from(photo.data));
    });

    it('keeps the long side within the limit', () => {
        const cut = warp(gradient(100, 50), WHOLE, 40);
        expect(Math.max(cut.width, cut.height)).toBe(40);
    });
});

describe('shapeOf', () => {
    it('finds the 1 : 1.414 of an A4 seen at an angle, within 2%', () => {
        const width = 4000;
        const height = 3000;
        const focal = 0.75 * width;
        const rotate = (
            point: [number, number, number],
            ax: number,
            ay: number,
        ): [number, number, number] => {
            const [x, y, z] = point;
            const y1 = y * Math.cos(ax) - z * Math.sin(ax);
            const z1 = y * Math.sin(ax) + z * Math.cos(ax);
            return [
                x * Math.cos(ay) + z1 * Math.sin(ay),
                y1,
                -x * Math.sin(ay) + z1 * Math.cos(ay),
            ];
        };
        const sheet: [number, number, number][] = [
            [-105, -148.5, 0],
            [105, -148.5, 0],
            [105, 148.5, 0],
            [-105, 148.5, 0],
        ];
        const points = sheet.map((corner) => {
            const [x, y, z] = rotate(
                corner,
                (25 * Math.PI) / 180,
                (20 * Math.PI) / 180,
            );
            const depth = z + 520;
            return {
                x: width / 2 + (focal * x) / depth,
                y: height / 2 + (focal * y) / depth,
            };
        });
        const corners = points.map((p) => ({
            x: p.x / width,
            y: p.y / height,
        })) as Corners;
        expect(
            corners.every((c) => c.x > 0 && c.x < 1 && c.y > 0 && c.y < 1),
        ).toBe(true);

        const size = shapeOf(corners, width, height);
        expect(size.width / size.height).toBeGreaterThan((210 / 297) * 0.98);
        expect(size.width / size.height).toBeLessThan((210 / 297) * 1.02);
    });

    it('gives the size of the photo for the whole photo', () => {
        const size = shapeOf(WHOLE, 400, 300);
        expect(size.width / size.height).toBeCloseTo(400 / 300, 1);
    });
});

describe('sideSize', () => {
    it('makes a card of 2000 x 1261', () => {
        const size = sideSize(2000, 1261);
        expect(size.card).toBe(true);
        expect(size.width).toBeCloseTo(85.6, 1);
        expect(size.height).toBeCloseTo(54.0, 1);
    });

    it('gives a page of a passport to what is not a card', () => {
        const size = sideSize(2000, 1408);
        expect(size.card).toBe(false);
        expect(size.width).toBeCloseTo(125, 1);
        expect(size.height).toBeCloseTo(88, 1);
    });

    it('works with a portrait photo too', () => {
        const size = sideSize(1261, 2000);
        expect(size.card).toBe(true);
        expect(size.height).toBeCloseTo(85.6, 1);
    });
});

describe('sheetLayout', () => {
    it('puts two cards at 63 and 180 mm', () => {
        const card = sideSize(2000, 1261);
        const { places } = sheetLayout([card, card]);
        expect(places[0].y).toBeCloseTo(63, 0);
        expect(places[1].y).toBeCloseTo(180, 0);
    });

    it('leaves 16.2 mm between four cards', () => {
        const card = sideSize(2000, 1261);
        const { places, scale } = sheetLayout([card, card, card, card]);
        expect(scale).toBe(1);
        expect(places[1].y - (places[0].y + places[0].height)).toBeCloseTo(
            16.2,
            1,
        );
        expect(places[0].y).toBeCloseTo(16.2, 1);
    });

    it('centres each side and shrinks them all when they do not fit', () => {
        const page = sideSize(2000, 1408);
        const { places, scale } = sheetLayout([page, page, page, page]);
        expect(scale).toBeLessThan(1);
        expect(places[0].x).toBeCloseTo((210 - places[0].width) / 2, 5);
    });
});

describe('isSheet', () => {
    it('takes the whole photo and a tilted sheet, clockwise', () => {
        expect(isSheet(WHOLE)).toBe(true);
        expect(
            isSheet([
                { x: 0.17, y: 0.12 },
                { x: 0.84, y: 0.09 },
                { x: 0.88, y: 0.88 },
                { x: 0.13, y: 0.91 },
            ]),
        ).toBe(true);
    });

    it('refuses crossed sides, a dent and a sheet with no area', () => {
        // two corners swapped: the sides cross
        expect(
            isSheet([
                { x: 0, y: 0 },
                { x: 1, y: 1 },
                { x: 1, y: 0 },
                { x: 0, y: 1 },
            ]),
        ).toBe(false);
        // a corner pushed inside
        expect(
            isSheet([
                { x: 0, y: 0 },
                { x: 1, y: 0 },
                { x: 0.3, y: 0.3 },
                { x: 0, y: 1 },
            ]),
        ).toBe(false);
        expect(
            isSheet([
                { x: 0.5, y: 0.5 },
                { x: 0.5, y: 0.5 },
                { x: 0.5, y: 0.5 },
                { x: 0.5, y: 0.5 },
            ]),
        ).toBe(false);
    });
});
