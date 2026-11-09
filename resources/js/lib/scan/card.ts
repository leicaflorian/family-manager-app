// Puts the sides of a card (front and back, or two cards) on one A4 page, as a photocopy would: finds where the
// card is in a side of the scanner (`findCard`), and draws the cards cut out straight, in a column, at their real
// size (`layout`, `sheetCanvas`). No model: on a scanner the background is the lid, even, and the size of a dot is
// known. Where the card is not found well, the page lets the cut be corrected by hand.
//
// A scanner does not always hand a whole A4: some cut the side to what lies on the glass. So the size of a dot
// does not come from the size of the side but from the dots per inch written in its JPEG (`densityOf`), or the
// ones asked of the scanner (`millimetreOf`).

import { SHEET, CARD_CORNER, sheetLayout } from './crop';
import type { Layout, Pixels, Place } from './crop';
import { improveSide, paperOf } from './improve';
import type { Improvement } from './improve';

export type Box = Place;
export type Size = { width: number; height: number };

/**
 * Where a card is in a side: the side is turned by `angle` degrees clockwise around its centre, which makes the
 * card straight, and `box` is the card in the side so turned, in dots of the side.
 */
export type Cut = { box: Box; angle: number };

/** What `findCard` found: `small` when it is so little that it is a card and not a sheet. */
export type Found = { cut: Cut; small: boolean };

/**
 * One card of a composed page: the JPEG of the side it is cut from, where, how many quarters of a turn clockwise
 * it is given on the page, and how many dots of that side make a millimetre. With `round` its corners are cut
 * rounded, as the card has them. Either way what is on the page is what the box of the cut holds, no more.
 */
export type Card = {
    image: Blob;
    cut: Cut;
    turns: number;
    millimetre: number;
    round: boolean;
};

/** A composed page: where each card goes, in millimetres from the top left corner, and by how much they were reduced to fit. */
export type Sheet = Layout;

/** How many sides a composed page takes: two cards, front and back. One more opens another page. */
export const MOST_PER_SHEET = 4;

/** Below this share of an A4, what was found is a card: one takes 7%, a page of a passport 18%, an A5 half. */
const SMALL_SHARE = 0.25;

/** A millimetre in inches. */
const INCH = 25.4;

/** The dots per inch a program writes in a JPEG when it does not know them: not believed, unless they were asked. */
const UNKNOWN_DENSITIES = [72, 96];

/** How much of a JPEG is read to find its dots per inch: they are written before the picture. */
const HEADER_BYTES = 65536;

/** How far from the colour of the lid a dot must be, in any of its colours, to be part of the card (0-255). */
const DIFFERENT = 20;

/** A row or a column with less of the card than this, in millimetres, is not part of it: a speck of dust. */
const LEAST_MARKS = 1.5;

/**
 * The dark line a scanner leaves along an edge of the glass is not a card: within this many millimetres of an
 * edge, a row or a column marked for more than `EDGE_LINE` of its length is cleared. A card does not reach that
 * share, not even the page of a passport.
 */
const EDGE_BAND = 5;
const EDGE_LINE = 0.8;

/**
 * Marks closer than this, in millimetres, are of the same thing: the edge of a card, its lines of text, what is
 * printed on it.
 */
const SAME_THING = 1.5;

/**
 * A thing with fewer marks than this share of the one with the most, or than this many square millimetres, is not
 * looked at: a speck of dust on the glass, a hair, a scratch. A row of them across the side would otherwise widen
 * the box as far as the farthest.
 */
const SPECK_SHARE = 0.01;
const SPECK = 4;

/**
 * The most a cut turns a side, in degrees, either way: a card put down any way is straight within this, standing
 * or lying, and the quarters of a turn do the rest.
 */
export const MOST_CUT_ANGLE = 45;

/** When the edges of the card do not agree on how crooked it is within this, in degrees, they are not believed. */
const TILT_AGREES = 1;

/**
 * The edges are read once the side is turned to the whole degree that suits the card best: what they still find
 * is small. More than this, in degrees, and they are not believed.
 */
const MOST_LEFT = 1.5;

/**
 * When the edges do not say (a light card), the turn that makes the box around the card the smallest is believed
 * only if that box is this much smaller than the one around the card as it lies: 2 degrees take 7% off, 5 take 16%.
 */
const CLEARLY_SMALLER = 0.95;

/** The quality of the JPEG of a composed page: mostly white, so it stays light even this high. */
const JPEG_QUALITY = 0.9;

/** Which dots of the picture are not the lid: 1 where the colour is far from the most common one. */
function marksOf(image: Pixels): Uint8Array {
    const { data, width, height } = image;
    const [red, green, blue] = paperOf(image);
    const marked = new Uint8Array(width * height);
    for (let index = 0, at = 0; index < marked.length; index++, at += 4) {
        const far = Math.max(
            Math.abs(data[at]! - red),
            Math.abs(data[at + 1]! - green),
            Math.abs(data[at + 2]! - blue),
        );
        if (far > DIFFERENT) marked[index] = 1;
    }
    return marked;
}

/** Takes away the marks of a dark line along an edge of the picture. */
function clearEdges(
    marked: Uint8Array,
    width: number,
    height: number,
    millimetre: number,
) {
    const band = Math.round(EDGE_BAND * millimetre);
    const rows = [...Array(Math.min(band, height)).keys()].flatMap((y) => [
        y,
        height - 1 - y,
    ]);
    for (const y of rows) {
        let count = 0;
        for (let x = 0; x < width; x++) count += marked[y * width + x]!;
        if (count > width * EDGE_LINE)
            marked.fill(0, y * width, (y + 1) * width);
    }
    const columns = [...Array(Math.min(band, width)).keys()].flatMap((x) => [
        x,
        width - 1 - x,
    ]);
    for (const x of columns) {
        let count = 0;
        for (let y = 0; y < height; y++) count += marked[y * width + x]!;
        if (count <= height * EDGE_LINE) continue;
        for (let y = 0; y < height; y++) marked[y * width + x] = 0;
    }
}

/**
 * Takes away the marks that are not of the card: the specks, and what lies all along an edge of the picture (the
 * rest of a dark line, the shadow of the lid). The marks near each other are put together in things first, so a
 * card with little on it is not taken for specks.
 */
function clearSpecks(
    marked: Uint8Array,
    width: number,
    height: number,
    millimetre: number,
) {
    const reach = Math.max(1, Math.round(SAME_THING * millimetre));
    // the dots within reach of a mark, first along the rows and then, from those, along the columns
    const spread = (
        from: Uint8Array,
        lines: number,
        length: number,
        at: (line: number, index: number) => number,
    ) => {
        const to = new Uint8Array(from.length);
        for (let line = 0; line < lines; line++) {
            for (const back of [false, true]) {
                let since = reach + 1;
                for (let step = 0; step < length; step++) {
                    const index = at(line, back ? length - 1 - step : step);
                    since = from[index] ? 0 : since + 1;
                    if (since <= reach) to[index] = 1;
                }
            }
        }
        return to;
    };
    const near = spread(
        spread(marked, height, width, (y, x) => y * width + x),
        width,
        height,
        (x, y) => y * width + x,
    );

    // each thing: how many marks it holds and where they are
    const things: {
        count: number;
        left: number;
        top: number;
        right: number;
        bottom: number;
    }[] = [];
    const thingOf = new Int32Array(marked.length).fill(-1);
    const stack = new Int32Array(marked.length);
    for (let start = 0; start < near.length; start++) {
        if (!near[start] || thingOf[start]! >= 0) continue;
        const thing = {
            count: 0,
            left: width,
            top: height,
            right: -1,
            bottom: -1,
        };
        let top = 0;
        stack[top++] = start;
        thingOf[start] = things.length;
        while (top) {
            const index = stack[--top]!;
            const x = index % width,
                y = (index - x) / width;
            if (marked[index]) {
                thing.count++;
                thing.left = Math.min(thing.left, x);
                thing.right = Math.max(thing.right, x);
                thing.top = Math.min(thing.top, y);
                thing.bottom = Math.max(thing.bottom, y);
            }
            for (const next of [
                x > 0 ? index - 1 : -1,
                x < width - 1 ? index + 1 : -1,
                index - width,
                index + width,
            ]) {
                if (
                    next < 0 ||
                    next >= near.length ||
                    !near[next] ||
                    thingOf[next]! >= 0
                )
                    continue;
                thingOf[next] = things.length;
                stack[top++] = next;
            }
        }
        things.push(thing);
    }

    const band = EDGE_BAND * millimetre;
    const least = Math.max(
        SPECK * millimetre ** 2,
        SPECK_SHARE * Math.max(0, ...things.map((thing) => thing.count)),
    );
    const kept = things.map(
        (thing) =>
            thing.count >= least &&
            thing.bottom >= band &&
            thing.top < height - band &&
            thing.right >= band &&
            thing.left < width - band,
    );
    for (let index = 0; index < marked.length; index++) {
        if (marked[index] && !kept[thingOf[index]!]) marked[index] = 0;
    }
}

/** From the first to the last count that reaches `least`: what holds fewer marks at the two ends is a speck. */
function stretch(counts: Uint32Array, least: number): [number, number] | null {
    let from = -1,
        to = -1;
    for (let index = 0; index < counts.length; index++) {
        if (counts[index]! < least) continue;
        if (from < 0) from = index;
        to = index;
    }
    return from < 0 ? null : [from, to];
}

/**
 * The box around the marks once the picture is turned by `angle` degrees clockwise around its centre, in dots of
 * the picture so turned. The rows that hold marks are found first, then the columns among those rows, then the
 * rows again among those columns: a speck beside the card or above it does not widen the box.
 */
function boxAt(
    marked: Uint8Array,
    width: number,
    height: number,
    angle: number,
    millimetre: number,
): Box | null {
    const radians = (angle * Math.PI) / 180;
    const cos = Math.cos(radians),
        sin = Math.sin(radians);
    const cx = width / 2,
        cy = height / 2;
    const size = Math.ceil(Math.hypot(width, height)) + 2,
        half = size / 2;
    const least = Math.max(2, Math.round(LEAST_MARKS * millimetre));

    // the column and the row a dot falls in once the picture is turned
    const across = (x: number, y: number) =>
        Math.round((x - cx) * cos - (y - cy) * sin + half);
    const down = (x: number, y: number) =>
        Math.round((x - cx) * sin + (y - cy) * cos + half);

    const tally = (
        along: typeof across,
        other: typeof across,
        within: [number, number] | null,
    ) => {
        const counts = new Uint32Array(size);
        for (let y = 0; y < height; y++) {
            for (let x = 0; x < width; x++) {
                if (!marked[y * width + x]) continue;
                if (within) {
                    const at = other(x, y);
                    if (at < within[0] || at > within[1]) continue;
                }
                counts[along(x, y)]!++;
            }
        }
        return stretch(counts, least);
    };

    const rows = tally(down, across, null);
    if (!rows) return null;
    const columns = tally(across, down, rows);
    if (!columns) return null;
    const tight = tally(down, across, columns) ?? rows;
    return {
        x: columns[0] - half + cx - 0.5,
        y: tight[0] - half + cy - 0.5,
        width: columns[1] - columns[0] + 1,
        height: tight[1] - tight[0] + 1,
    };
}

/** The slope of the line through the points, the ones far from it left out; null with too few to tell. */
function slopeOf(points: [number, number][]): number | null {
    let kept = points;
    for (let pass = 0; pass < 2; pass++) {
        if (kept.length < 12) return null;
        let sumX = 0,
            sumY = 0;
        for (const [x, y] of kept) {
            sumX += x;
            sumY += y;
        }
        const meanX = sumX / kept.length,
            meanY = sumY / kept.length;
        let over = 0,
            under = 0;
        for (const [x, y] of kept) {
            over += (x - meanX) * (y - meanY);
            under += (x - meanX) ** 2;
        }
        if (!under) return null;
        const slope = over / under;
        if (pass) return slope;
        // a round corner, a notch, the shadow: the points more than two dots from the line do not count
        kept = kept.filter(
            ([x, y]) => Math.abs(y - meanY - slope * (x - meanX)) <= 2,
        );
    }
    return null;
}

/**
 * By how much the card is still turned once the picture is turned by `angle` degrees clockwise around its centre,
 * in degrees clockwise, read from its four edges along the middle of each; `box` is the card in the picture so
 * turned. Null when the edges do not agree (a light card, whose edge does not stand out from the lid). It reads
 * well a card that is nearly straight: on one more crooked the middle of an edge holds a piece of the next.
 */
function tiltOf(
    marked: Uint8Array,
    width: number,
    height: number,
    angle: number,
    box: Box,
): number | null {
    const radians = (angle * Math.PI) / 180;
    const cos = Math.cos(radians),
        sin = Math.sin(radians);
    const cx = width / 2,
        cy = height / 2;
    const left = Math.round(box.x),
        top = Math.round(box.y);
    const wide = Math.max(1, Math.round(box.width)),
        tall = Math.max(1, Math.round(box.height));
    // the first and the last mark of each column and of each row of the box, where they fall once turned
    const highest = new Float32Array(wide).fill(Infinity),
        lowest = new Float32Array(wide).fill(-Infinity);
    const leftmost = new Float32Array(tall).fill(Infinity),
        rightmost = new Float32Array(tall).fill(-Infinity);
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            if (!marked[y * width + x]) continue;
            const across = (x - cx) * cos - (y - cy) * sin + cx,
                down = (x - cx) * sin + (y - cy) * cos + cy;
            const column = Math.round(across) - left,
                row = Math.round(down) - top;
            if (column < 0 || column >= wide || row < 0 || row >= tall)
                continue;
            if (down < highest[column]!) highest[column] = down;
            if (down > lowest[column]!) lowest[column] = down;
            if (across < leftmost[row]!) leftmost[row] = across;
            if (across > rightmost[row]!) rightmost[row] = across;
        }
    }

    const insetX = Math.round(wide * 0.2),
        insetY = Math.round(tall * 0.2);
    const edges: [number, number][][] = [[], [], [], []];
    for (let column = insetX; column < wide - insetX; column++) {
        if (highest[column] === Infinity) continue;
        edges[0]!.push([column, highest[column]!]);
        edges[1]!.push([column, lowest[column]!]);
    }
    for (let row = insetY; row < tall - insetY; row++) {
        if (leftmost[row] === Infinity) continue;
        edges[2]!.push([row, leftmost[row]!]);
        edges[3]!.push([row, rightmost[row]!]);
    }

    // turned clockwise, the top and the bottom go down to the right, and the sides go left going down
    const angles = edges
        .map((points, index) => {
            const slope = slopeOf(points);
            return slope === null
                ? null
                : ((index < 2 ? 1 : -1) * Math.atan(slope) * 180) / Math.PI;
        })
        .filter((angle): angle is number => angle !== null)
        .sort((a, b) => a - b);
    if (angles.length < 2) return null;
    const low = angles[Math.floor((angles.length - 1) / 2)]!,
        high = angles[Math.ceil((angles.length - 1) / 2)]!;
    return high - low > TILT_AGREES ? null : (low + high) / 2;
}

/**
 * The turn, among `angles` (degrees clockwise, around the centre of the picture), that makes the box around the
 * marks the smallest, with how large each box is, in dots: a card is straight in the smallest box that holds it.
 * Of two turns that do as well, the first.
 */
function tightest(
    marked: Uint8Array,
    width: number,
    height: number,
    angles: number[],
    least: number,
) {
    const cx = width / 2,
        cy = height / 2;
    const size = Math.ceil(Math.hypot(width, height)) + 2,
        half = size / 2;
    let count = 0;
    for (let index = 0; index < marked.length; index++) count += marked[index]!;
    const xs = new Float32Array(count),
        ys = new Float32Array(count);
    for (let y = 0, at = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            if (!marked[y * width + x]) continue;
            xs[at] = x - cx;
            ys[at++] = y - cy;
        }
    }

    const across = new Uint32Array(size),
        down = new Uint32Array(size);
    const areas = angles.map((angle) => {
        const radians = (angle * Math.PI) / 180;
        const cos = Math.cos(radians),
            sin = Math.sin(radians);
        across.fill(0);
        down.fill(0);
        for (let at = 0; at < count; at++) {
            across[Math.round(xs[at]! * cos - ys[at]! * sin + half)]!++;
            down[Math.round(xs[at]! * sin + ys[at]! * cos + half)]!++;
        }
        const columns = stretch(across, least),
            rows = stretch(down, least);
        return columns && rows
            ? (columns[1] - columns[0] + 1) * (rows[1] - rows[0] + 1)
            : Infinity;
    });
    const best = areas.indexOf(Math.min(...areas));
    return { angle: angles[best]!, area: areas[best]!, areas };
}

/**
 * By how much the picture is to be turned, in degrees clockwise, for the card to be straight: 0 when it cannot be
 * told. First the whole degree that makes the box around the card the smallest, then, with the picture so turned,
 * what the edges of the card still say. When they do not say, the smallest box is looked for by tenths around
 * that degree, and believed only when it is clearly smaller than the box around the card as it lies.
 */
function angleOf(
    marked: Uint8Array,
    width: number,
    height: number,
    millimetre: number,
): number {
    const least = Math.max(2, Math.round(LEAST_MARKS * millimetre));
    // 0, 1, -1, 2, -2…: of two turns that do as well, the smaller
    const whole = [...Array(2 * MOST_CUT_ANGLE + 1).keys()].map((step) =>
        step % 2 ? (step + 1) / 2 : -step / 2,
    );
    const rough = tightest(marked, width, height, whole, least);

    const box = boxAt(marked, width, height, rough.angle, millimetre);
    const left = box && tiltOf(marked, width, height, rough.angle, box);
    if (left !== null && Math.abs(left) <= MOST_LEFT)
        return Math.round((rough.angle - left) * 10) / 10 || 0;

    const tenths = [...Array(19).keys()].map(
        (step) => Math.round((rough.angle + (step - 9) / 10) * 10) / 10,
    );
    const fine = tightest(
        marked,
        width,
        height,
        [rough.angle, ...tenths],
        least,
    );
    const within = Math.min(
        MOST_CUT_ANGLE,
        Math.max(-MOST_CUT_ANGLE, fine.angle),
    );
    return fine.area < rough.areas[0]! * CLEARLY_SMALLER ? within || 0 : 0;
}

/**
 * Where the card is in a picture of a side, along its edge, in dots of the picture, of which `millimetre` make a millimetre; null
 * on a side with nothing on it. A card that lies crooked on the glass is found with the angle that makes it
 * straight. What is found on a side that is not small is the box of what is written on the sheet, straight.
 */
export function findCard(image: Pixels, millimetre: number): Found | null {
    const { width, height } = image;
    const marked = marksOf(image);
    clearEdges(marked, width, height, millimetre);
    clearSpecks(marked, width, height, millimetre);
    const flat = boxAt(marked, width, height, 0, millimetre);
    if (!flat) return null;

    // a card that lies crooked takes a box larger than itself: it is looked for in a box up to twice a card
    const limit = SHEET.width * SHEET.height * SMALL_SHARE * millimetre ** 2;
    const turn =
        flat.width * flat.height < 2 * limit
            ? angleOf(marked, width, height, millimetre)
            : 0;
    const straight =
        (turn && boxAt(marked, width, height, turn, millimetre)) || flat;
    const small = straight.width * straight.height < limit;
    const [box, angle] = small ? [straight, turn] : [flat, 0];

    let [left, top, right, bottom] = [
        box.x,
        box.y,
        box.x + box.width,
        box.y + box.height,
    ];
    // straight, the card ends where the side does. Turned around the centre of the side, a card that lay near an
    // edge is partly out of where the side was: there the box is left as it is
    if (!angle)
        [left, top, right, bottom] = [
            Math.max(0, left),
            Math.max(0, top),
            Math.min(width, right),
            Math.min(height, bottom),
        ];
    return {
        cut: {
            box: { x: left, y: top, width: right - left, height: bottom - top },
            angle,
        },
        small,
    };
}

/** The same cut in a picture `factor` times as large: the card is looked for on a small copy of the side. */
export function scaleCut(cut: Cut, factor: number): Cut {
    const { x, y, width, height } = cut.box;
    return {
        box: {
            x: x * factor,
            y: y * factor,
            width: width * factor,
            height: height * factor,
        },
        angle: cut.angle,
    };
}

/**
 * The dots per inch a JPEG says it has, in its JFIF or its Exif header; null when it does not say, or says two
 * different ones across and down.
 */
export async function densityOf(jpeg: Blob): Promise<number | null> {
    const bytes = new DataView(await jpeg.slice(0, HEADER_BYTES).arrayBuffer());
    const text = (at: number, length: number) =>
        at + length <= bytes.byteLength
            ? String.fromCharCode(
                  ...Array.from({ length }, (_, index) =>
                      bytes.getUint8(at + index),
                  ),
              )
            : '';
    const same = (across: number, down: number, perInch: number) =>
        across > 0 && across === down ? Math.round(across * perInch) : null;

    if (bytes.byteLength < 4 || bytes.getUint16(0) !== 0xffd8) return null;
    let exif: number | null = null;
    // the pieces before the picture: two bytes that say which, two that say how long
    for (
        let at = 2;
        at + 4 <= bytes.byteLength && bytes.getUint8(at) === 0xff;
    ) {
        const piece = bytes.getUint8(at + 1),
            length = bytes.getUint16(at + 2),
            from = at + 4;
        if (piece === 0xda || length < 2) break;
        if (
            piece === 0xe0 &&
            text(from, 5) === 'JFIF\0' &&
            from + 12 <= bytes.byteLength
        ) {
            // 1 is dots per inch, 2 per centimetre; 0 is only the shape of a dot
            const unit = bytes.getUint8(from + 7);
            const stated =
                unit === 1 || unit === 2
                    ? same(
                          bytes.getUint16(from + 8),
                          bytes.getUint16(from + 10),
                          unit === 1 ? 1 : INCH / 10,
                      )
                    : null;
            if (stated) return stated;
        }
        if (piece === 0xe1 && text(from, 6) === 'Exif\0\0' && exif === null)
            exif = from + 6;
        at = from + length - 2;
    }
    if (exif === null || exif + 8 > bytes.byteLength) return null;

    // Exif: a list of entries of twelve bytes, with the numbers written from either end
    const little = text(exif, 2) === 'II';
    const list = exif + bytes.getUint32(exif + 4, little);
    if (list + 2 > bytes.byteLength) return null;
    let across = 0,
        down = 0,
        unit = 2;
    const fraction = (at: number) => {
        const where = exif! + bytes.getUint32(at, little);
        if (where + 8 > bytes.byteLength) return 0;
        const under = bytes.getUint32(where + 4, little);
        return under ? bytes.getUint32(where, little) / under : 0;
    };
    for (
        let index = 0, count = bytes.getUint16(list, little);
        index < count;
        index++
    ) {
        const entry = list + 2 + index * 12;
        if (entry + 12 > bytes.byteLength) break;
        const tag = bytes.getUint16(entry, little);
        if (tag === 0x011a) across = fraction(entry + 8);
        else if (tag === 0x011b) down = fraction(entry + 8);
        else if (tag === 0x0128) unit = bytes.getUint16(entry + 8, little);
    }
    return unit === 2 || unit === 3
        ? same(across, down, unit === 2 ? 1 : INCH / 10)
        : null;
}

/**
 * How many dots of a side of the scanner make a millimetre: from the dots per inch its JPEG states (`stated`),
 * else from the ones asked of the scanner (`asked`). With neither, the side is taken for a whole A4, 210 mm across.
 */
export function millimetreOf(
    side: Size,
    stated: number | null,
    asked: number | null,
): number {
    const believed =
        stated && (stated === asked || !UNKNOWN_DENSITIES.includes(stated))
            ? stated
            : asked;
    return believed
        ? believed / INCH
        : Math.min(side.width, side.height) / SHEET.width;
}

/** How large a card is on the page, in millimetres, as it is turned. */
export function sizeOf(card: Pick<Card, 'cut' | 'turns' | 'millimetre'>): Size {
    const width = card.cut.box.width / card.millimetre,
        height = card.cut.box.height / card.millimetre;
    return card.turns % 2
        ? { width: height, height: width }
        : { width, height };
}

/** A rectangle with its corners rounded by `radius`, as the path of the canvas. */
function outline(
    context: CanvasRenderingContext2D,
    x: number,
    y: number,
    width: number,
    height: number,
    radius: number,
) {
    context.beginPath();
    context.moveTo(x + radius, y);
    context.arcTo(x + width, y, x + width, y + height, radius);
    context.arcTo(x + width, y + height, x, y + height, radius);
    context.arcTo(x, y + height, x, y, radius);
    context.arcTo(x, y, x + width, y, radius);
    context.closePath();
}

/**
 * Where the cards go on the page: in a column, each in the middle of the width, with the height left over shared
 * equally above, between and below. At their real size; when they do not fit so, all reduced by the same measure.
 * The same rule as the photos of a card on one page, so it is `sheetLayout` of `crop.ts`.
 */
export function layout(sizes: Size[]): Sheet {
    return sheetLayout(sizes);
}

/**
 * The composed page drawn: white, with as many dots as the finest of the sides the cards come from, and each card
 * cut from the JPEG of its side straight onto it, so it is resampled once.
 */
export async function sheetCanvas(cards: Card[]): Promise<HTMLCanvasElement> {
    const millimetre = Math.max(1, ...cards.map((card) => card.millimetre));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(SHEET.width * millimetre);
    canvas.height = Math.round(SHEET.height * millimetre);
    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (!context) throw new Error('canvas');
    // the paper is white in both themes: it is a sheet to print, not part of the page
    context.fillStyle = 'white';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.imageSmoothingQuality = 'high';

    const { places } = layout(cards.map(sizeOf));
    for (const [index, card] of cards.entries()) {
        const place = places[index]!;
        const { box, angle } = card.cut;
        const bitmap = await createImageBitmap(card.image);
        try {
            const radius = card.round
                ? Math.min(
                      CARD_CORNER * card.millimetre,
                      box.width / 2,
                      box.height / 2,
                  )
                : 0;
            // how large a dot of the side is on the page
            const zoom =
                (place.width * millimetre) /
                (card.turns % 2 ? box.height : box.width);
            context.save();
            // read from the bottom: the side is turned around its centre, the middle of the box is brought to the
            // origin, only the box is let through, and it goes, turned and as large as it must be, to its place
            context.translate(
                (place.x + place.width / 2) * millimetre,
                (place.y + place.height / 2) * millimetre,
            );
            context.rotate((card.turns * Math.PI) / 2);
            context.scale(zoom, zoom);
            outline(
                context,
                -box.width / 2,
                -box.height / 2,
                box.width,
                box.height,
                radius,
            );
            context.clip();
            context.translate(
                -(box.x + box.width / 2),
                -(box.y + box.height / 2),
            );
            context.translate(bitmap.width / 2, bitmap.height / 2);
            context.rotate((angle * Math.PI) / 180);
            context.translate(-bitmap.width / 2, -bitmap.height / 2);
            context.drawImage(bitmap, 0, 0);
            context.restore();
        } finally {
            bitmap.close();
        }
    }
    return canvas;
}

/**
 * Draws some pixels on a canvas, which takes their size.
 *
 * @param canvas - the canvas
 * @param picture - the pixels
 * @throws if the browser has no canvas for it
 */
export function drawOn(canvas: HTMLCanvasElement, picture: Pixels): void {
    canvas.width = picture.width;
    canvas.height = picture.height;
    const context = canvas.getContext('2d');
    if (!context) throw new Error('canvas');
    const drawn = context.createImageData(picture.width, picture.height);
    drawn.data.set(picture.data);
    context.putImageData(drawn, 0, 0);
}

/**
 * What *Improve* was asked, done on the composed page already drawn: the page is compressed once, after it.
 *
 * @param canvas - the composed page
 * @param improvement - what is asked
 * @throws if the browser has no canvas for it
 */
export function improveCanvas(
    canvas: HTMLCanvasElement,
    improvement: Improvement,
): void {
    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (!context) throw new Error('canvas');
    drawOn(
        canvas,
        improveSide(
            context.getImageData(0, 0, canvas.width, canvas.height),
            improvement,
        ),
    );
}

/** The JPEG of the composed page. */
export async function jpegOf(canvas: HTMLCanvasElement): Promise<Blob> {
    const blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY),
    );
    if (!blob) throw new Error('jpeg');
    return blob;
}
