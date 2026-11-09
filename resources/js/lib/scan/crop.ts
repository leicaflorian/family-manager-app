/**
 * What "Ritaglia" does to a photo, in the phone: cuts the sheet out of the photo and straightens it (`warp`),
 * and turns a photo taken lying on its side (`turn`, with the corners of the sheet: `turnCorners`,
 * `sourceCorners`). Plain arithmetic on the pixels, no model: a model that redraws the picture could change
 * a digit. Ported from `phone-scan/public/crop.js`; `enhance` and `grey` live in `improve.ts`.
 */

/** A picture as a canvas gives it: four bytes a pixel (RGBA). */
export type Pixels = { width: number; height: number; data: Uint8ClampedArray };

/** A point of the photo, each coordinate from 0 to 1. */
export type Point = { x: number; y: number };

/** The four corners of a sheet, clockwise from the top left one. */
export type Corners = [Point, Point, Point, Point];

/** Quarters of a turn, clockwise. */
export type Turns = 0 | 1 | 2 | 3;

/** The size in millimetres of a side drawn on the page. */
export type SideSize = { width: number; height: number; card: boolean };

/** A place on the page, in millimetres. */
export type Place = { x: number; y: number; width: number; height: number };

/** The sides on the page: the common reduction and the place of each. */
export type Layout = { scale: number; places: Place[] };

/** The long side of a cut out sheet, in pixels. */
export const LONG_SIDE = 2000;
/** The long side of the photo the sheet is cut from, in pixels. */
export const SOURCE_LONG_SIDE = 3000;
/** The corners of "Tutta la foto": the whole photo. */
export const WHOLE: Corners = [
    { x: 0, y: 0 },
    { x: 1, y: 0 },
    { x: 1, y: 1 },
    { x: 0, y: 1 },
];

/** The focal length of the main camera of a phone (26 mm on a 35 mm film), as a part of the long side of the photo. */
const PHONE_FOCAL = 0.75;
/** How much the sheet must be tilted, each of the two ways, for the focal length to be worked out from its corners. */
const TILTED = 0.08;

/** A three-number vector, for the homogeneous coordinates of `shapeOf`. */
type Vector = [number, number, number];

/** The distance between two points. */
const distance = (a: Point, b: Point): number =>
    Math.hypot(a.x - b.x, a.y - b.y);
/** The cross product of two vectors. */
const crossOf = (a: Vector, b: Vector): Vector => [
    a[1] * b[2] - a[2] * b[1],
    a[2] * b[0] - a[0] * b[2],
    a[0] * b[1] - a[1] * b[0],
];
/** The dot product of two vectors. */
const dot = (a: Vector, b: Vector): number =>
    a[0] * b[0] + a[1] * b[1] + a[2] * b[2];

/** Reduces any number of turns to 0..3. */
const quartersOf = (turns: number): Turns => (((turns % 4) + 4) % 4) as Turns;

/** The corners in pixels of the photo. */
function inPixels(corners: Corners, width: number, height: number): Corners {
    return corners.map((corner) => ({
        x: corner.x * width,
        y: corner.y * height,
    })) as Corners;
}

/**
 * Width and height of the real sheet, in pixels of the photo, from its four corners. A sheet photographed at
 * an angle looks shorter than it is: the lengths of its sides in the photo would squash it. The true shape
 * comes from the geometry of the camera (Zhang and He, "Whiteboard scanning and image enhancement", 2004),
 * taking the centre of the photo as the point the camera looks at.
 *
 * The formula needs the focal length. It can work it out from the corners only when the sheet is tilted two
 * ways at once, and badly when one of the two is slight; otherwise the one of the main camera of a phone is
 * taken (`PHONE_FOCAL`).
 *
 * @param corners the four corners of the sheet, from 0 to 1
 * @param photoWidth width of the photo in pixels
 * @param photoHeight height of the photo in pixels
 * @returns the size of the sheet as large as it is in the photo, in its true shape
 */
export function shapeOf(
    corners: Corners,
    photoWidth: number,
    photoHeight: number,
): { width: number; height: number } {
    const points = inPixels(corners, photoWidth, photoHeight);
    const shape = shapeRatio(points, photoWidth, photoHeight);
    const [p0, p1, p2, p3] = points;
    const seenWidth = Math.max(distance(p0, p1), distance(p3, p2));
    const seenHeight = Math.max(distance(p0, p3), distance(p1, p2));
    const width = Math.max(seenWidth, seenHeight * shape);
    return { width, height: width / shape };
}

/** Width divided by height of the real sheet, from its corners in pixels. */
function shapeRatio(
    points: Corners,
    photoWidth: number,
    photoHeight: number,
): number {
    const [p0, p1, p2, p3] = points;
    const flat =
        (distance(p0, p1) + distance(p3, p2)) /
        (distance(p0, p3) + distance(p1, p2));

    const centred = (p: Point): Vector => [
        p.x - photoWidth / 2,
        p.y - photoHeight / 2,
        1,
    ];
    const m1 = centred(p0);
    const m2 = centred(p1);
    const m3 = centred(p3);
    const m4 = centred(p2);
    const k2 = dot(crossOf(m1, m4), m3) / dot(crossOf(m2, m4), m3);
    const k3 = dot(crossOf(m1, m4), m2) / dot(crossOf(m3, m4), m2);
    const n2: Vector = [k2 * m2[0] - m1[0], k2 * m2[1] - m1[1], k2 - 1];
    const n3: Vector = [k3 * m3[0] - m1[0], k3 * m3[1] - m1[1], k3 - 1];
    const longSide = Math.max(photoWidth, photoHeight);
    let focal = (PHONE_FOCAL * longSide) ** 2;
    if (Math.abs(n2[2]) > TILTED && Math.abs(n3[2]) > TILTED) {
        const measured = -(n2[0] * n3[0] + n2[1] * n3[1]) / (n2[2] * n3[2]);
        // a wide angle or a zoom, not a mistake: believed
        if (measured > (0.3 * longSide) ** 2 && measured < (3 * longSide) ** 2)
            focal = measured;
    }
    const shape = Math.sqrt(
        ((n2[0] ** 2 + n2[1] ** 2) / focal + n2[2] ** 2) /
            ((n3[0] ** 2 + n3[1] ** 2) / focal + n3[2] ** 2),
    );
    // an answer far from what the photo shows comes from corners placed badly, not from the perspective
    return Number.isFinite(shape) && shape > flat / 2 && shape < flat * 2
        ? shape
        : flat;
}

/**
 * The size of the straightened sheet: as large as it is in the photo, in its true shape, within `longSide`.
 */
function sizeOf(
    corners: Corners,
    photoWidth: number,
    photoHeight: number,
    longSide: number,
): { width: number; height: number } {
    const { width, height } = shapeOf(corners, photoWidth, photoHeight);
    const scale = Math.min(1, longSide / Math.max(width, height, 1));
    return {
        width: Math.max(1, Math.round(width * scale)),
        height: Math.max(1, Math.round(height * scale)),
    };
}

/**
 * The sheet cut out of the photo and made a rectangle again (projection of Heckbert, 1989).
 *
 * @param source the photo
 * @param corners the four corners of the sheet, from 0 to 1, clockwise from the top left one
 * @param longSide the long side of the result, at most
 * @returns the straightened sheet
 */
export function warp(
    source: Pixels,
    corners: Corners,
    longSide: number,
): Pixels {
    const [p0, p1, p2, p3] = inPixels(corners, source.width, source.height);
    const { width, height } = sizeOf(
        corners,
        source.width,
        source.height,
        longSide,
    );

    // the projection that takes the unit square onto the four corners
    const dx1 = p1.x - p2.x;
    const dx2 = p3.x - p2.x;
    const dx3 = p0.x - p1.x + p2.x - p3.x;
    const dy1 = p1.y - p2.y;
    const dy2 = p3.y - p2.y;
    const dy3 = p0.y - p1.y + p2.y - p3.y;
    const divisor = dx1 * dy2 - dx2 * dy1;
    const g = divisor ? (dx3 * dy2 - dx2 * dy3) / divisor : 0;
    const h = divisor ? (dx1 * dy3 - dx3 * dy1) / divisor : 0;
    const a = p1.x - p0.x + g * p1.x;
    const b = p3.x - p0.x + h * p3.x;
    const c = p0.x;
    const d = p1.y - p0.y + g * p1.y;
    const e = p3.y - p0.y + h * p3.y;
    const f = p0.y;

    const from = source.data;
    const sw = source.width;
    const sh = source.height;
    const data = new Uint8ClampedArray(width * height * 4);

    for (let row = 0; row < height; row++) {
        const v = (row + 0.5) / height;
        for (let column = 0; column < width; column++) {
            const u = (column + 0.5) / width;
            const w = g * u + h * v + 1;
            // the point of the photo this pixel comes from, between four of its pixels
            const x = Math.min(
                sw - 1,
                Math.max(0, (a * u + b * v + c) / w - 0.5),
            );
            const y = Math.min(
                sh - 1,
                Math.max(0, (d * u + e * v + f) / w - 0.5),
            );
            const x0 = Math.floor(x);
            const y0 = Math.floor(y);
            const x1 = Math.min(sw - 1, x0 + 1);
            const y1 = Math.min(sh - 1, y0 + 1);
            const fx = x - x0;
            const fy = y - y0;
            const i00 = (y0 * sw + x0) * 4;
            const i10 = (y0 * sw + x1) * 4;
            const i01 = (y1 * sw + x0) * 4;
            const i11 = (y1 * sw + x1) * 4;
            const out = (row * width + column) * 4;
            for (let channel = 0; channel < 3; channel++) {
                const top =
                    from[i00 + channel] +
                    (from[i10 + channel] - from[i00 + channel]) * fx;
                const bottom =
                    from[i01 + channel] +
                    (from[i11 + channel] - from[i01 + channel]) * fx;
                data[out + channel] = top + (bottom - top) * fy;
            }
            data[out + 3] = 255;
        }
    }
    return { data, width, height };
}

/**
 * The same picture turned clockwise by so many quarters of a turn ("Ruota").
 *
 * @param image the picture, left as it is
 * @param turns quarters of a turn
 * @returns a new picture
 */
export function turn(image: Pixels, turns: Turns): Pixels {
    const { data, width, height } = image;
    const quarters = quartersOf(turns);
    const sideways = quarters % 2 === 1;
    const w = sideways ? height : width;
    const h = sideways ? width : height;
    // a pixel moved whole, four bytes at a time
    const from = new Uint32Array(data.buffer, data.byteOffset, width * height);
    const to = new Uint32Array(w * h);
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const at =
                quarters === 1
                    ? x * w + (height - 1 - y)
                    : quarters === 2
                      ? (height - 1 - y) * w + (width - 1 - x)
                      : quarters === 3
                        ? (width - 1 - x) * w + y
                        : y * w + x;
            to[at] = from[y * width + x];
        }
    }
    return { data: new Uint8ClampedArray(to.buffer), width: w, height: h };
}

/**
 * The corners of the sheet after the photo has been turned clockwise by so many quarters: each point goes
 * round with the photo, `(x, y)` becomes `(1 - y, x)`, and the corners change name with it (the bottom left
 * one is now the top left one), so the first is always the one at the top left of what is on the screen.
 *
 * @param corners corners before the turn
 * @param turns quarters of a turn
 * @returns corners on the turned photo
 */
export function turnCorners(corners: Corners, turns: Turns): Corners {
    let turned = corners;
    for (let quarter = quartersOf(turns); quarter > 0; quarter--) {
        turned = [turned[3], turned[0], turned[1], turned[2]].map((corner) => ({
            x: 1 - corner.y,
            y: corner.x,
        })) as Corners;
    }
    return turned;
}

/**
 * Where the corners placed on a turned photo are in the photo as it was taken, in the same order. `warp` cuts
 * the sheet out of the photo as taken with these, and the sheet comes out as it was seen on the screen: the
 * pixels of the whole photo are never turned, which would take its memory twice.
 *
 * @param corners corners placed on the turned photo
 * @param turns quarters of a turn the photo was given
 * @returns corners on the photo as taken
 */
export function sourceCorners(corners: Corners, turns: Turns): Corners {
    let source = corners;
    for (let quarter = quartersOf(turns); quarter > 0; quarter--) {
        source = source.map((corner) => ({
            x: corner.y,
            y: 1 - corner.x,
        })) as Corners;
    }
    return source;
}

/**
 * Whether the corners, in the order of the handles, make a sheet: clockwise, no crossed sides, no dent. A sheet
 * that is not one cannot be cut out ("Gli angoli si incrociano").
 *
 * @param corners the four corners, clockwise from the top left one
 * @returns true when they can be cut out
 */
export function isSheet(corners: Corners): boolean {
    return corners.every((a, index) => {
        const b = corners[(index + 1) % 4];
        const c = corners[(index + 2) % 4];

        return (b.x - a.x) * (c.y - b.y) - (b.y - a.y) * (c.x - b.x) > 0.0005;
    });
}

// ---- the photos of a card on one page ("Su una pagina sola")

/** An A4 sheet, in millimetres. */
export const SHEET = { width: 210, height: 297 };
/** The least white between two sides, and above and below them; and at the sides of the widest. */
const LEAST_GAP = 8;
const SIDE_MARGIN = 10;
/** The radius of the round corners of a card, in millimetres. */
export const CARD_CORNER = 3.18;
/** The long side of a card the size of a credit card, and its shape range (it is 1.59). */
const CARD_LONG = 85.6;
const CARD_SHAPE = [1.5, 1.65] as const;
/** The long side given to what is not a card: the one of a page of a passport. */
const OTHER_LONG = 125;

/**
 * How large a cut out photo is drawn, in millimetres. A photo does not know the size of what it shows: one
 * with the shape of a card gets the size of a card (and rounded corners), any other the one of a page of a
 * passport. The shape of the photo is kept.
 *
 * @param width width of the cut out photo
 * @param height height of the cut out photo
 * @returns the size in millimetres and whether it is a card
 */
export function sideSize(width: number, height: number): SideSize {
    const long = Math.max(width, height);
    const short = Math.max(1, Math.min(width, height));
    const card = long / short >= CARD_SHAPE[0] && long / short <= CARD_SHAPE[1];
    const millimetre = (card ? CARD_LONG : OTHER_LONG) / long;
    return { width: width * millimetre, height: height * millimetre, card };
}

/**
 * Where the sides go on the page: in a column, each in the middle of the width, with the height left over
 * shared equally above, between and below. At their size; when they do not fit so, all reduced by the same
 * measure.
 *
 * @param sizes the sides, in millimetres, top to bottom
 * @returns the reduction and the place of each side
 */
export function sheetLayout(
    sizes: Pick<SideSize, 'width' | 'height'>[],
): Layout {
    const tall = sizes.reduce((total, size) => total + size.height, 0);
    const wide = Math.max(0, ...sizes.map((size) => size.width));
    const scale = Math.min(
        1,
        (SHEET.height - (sizes.length + 1) * LEAST_GAP) / tall,
        (SHEET.width - 2 * SIDE_MARGIN) / wide,
    );
    const gap = (SHEET.height - tall * scale) / (sizes.length + 1);
    let y = gap;
    const places = sizes.map((size) => {
        const place = {
            x: (SHEET.width - size.width * scale) / 2,
            y,
            width: size.width * scale,
            height: size.height * scale,
        };
        y += place.height + gap;
        return place;
    });
    return { scale, places };
}
