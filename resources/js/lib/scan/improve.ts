// What "Improve" and "Black and white" do to a side, in the browser: `enhance` evens out the light and deepens the
// ink, `grey` takes the colours away, `skewOf` and `turnBy` find and undo the few degrees of a crooked sheet. Plain
// arithmetic on the pixels, no model: a model that redraws the side could change a digit. One copy, ported from the
// old extensions of Directus (`phone-scan/public/crop.js` and `document-reader/.../scan/improve.ts`).
import type { Pixels } from './crop';

/** What is switched on at the start (decision 5 of the plan): Improve and Black and white on, Straighten off. */
export const IMPROVE_DEFAULTS = {
    enhance: true,
    grey: true,
    dewarp: false,
} as const;

/** Where the paper should end up after `enhance`: almost white, not burnt. */
const PAPER = 248;
/** What is darker than this after the light is evened out becomes black: the ink gets deeper. */
const BLACK = 24;
/**
 * Past the strength of the phone the contrast grows: at `MOST_STRENGTH` what is darker than `BLACK_MOST` is black
 * and what is lighter than `WHITE_LEAST` is white. What is darker than about 185 gets darker than on the phone (the
 * text, a pencil), what is lighter goes to white (the shade of the paper, the writing of the other side showing
 * through).
 */
const BLACK_MOST = 96;
const WHITE_LEAST = 224;
/** A dark area is not paper in the shade (a photo, a logo): its light is not raised more than this. */
const MAX_GAIN = 3.5;

/** The long side a picture is brought to before `skewOf`: a tenth of a degree moves the end of a line by one dot. */
export const SKEW_LONG_SIDE = 850;
/** How far a crooked sheet is looked for, each way, in degrees: a feeder does not take a sheet more crooked than this. */
const SKEW_MOST = 8;
/** The part of each edge left out: the frame of the scanner is straight even when the sheet is not. */
const SKEW_BORDER = 0.03;
/** How much darker than the paper a dot must be to count as ink (0-255). */
const INK_DARKER = 40;
/** With fewer dots of ink than this there is nothing to measure; with more than `SKEW_POINTS` some are skipped. */
const SKEW_FEWEST = 300;
const SKEW_POINTS = 60000;
/**
 * How much sharper than the average the lines must be at the angle found for it to be believed. A page of text is
 * at 2 or more, a photo stays near 1: when in doubt the side is left alone, and can be turned by hand.
 */
const SKEW_SURE = 1.3;

/** The lightness of the dot at `at` (index of its red byte), 0-255. */
const lightOf = (data: Uint8ClampedArray, at: number): number =>
    (data[at]! * 77 + data[at + 1]! * 150 + data[at + 2]! * 29) >> 8;

/** A colour as red, green and blue. */
type Colour = [number, number, number];

/** The strength of `enhance` at its most: twice what the phone does, which is 1. */
const MOST_STRENGTH = 2;

/** The colour of the paper: the average of the dots that have the most common lightness. A sheet is not always white. */
export function paperOf(image: Pixels): Colour {
    const { data, width, height } = image;
    const step = Math.max(1, Math.round(Math.max(width, height) / 400));
    const counts = Array.from<number>({ length: 256 }).fill(0);
    for (let y = 0; y < height; y += step) {
        for (let x = 0; x < width; x += step)
            counts[lightOf(data, (y * width + x) * 4)]!++;
    }
    const paper = counts.indexOf(Math.max(...counts));

    let red = 0,
        green = 0,
        blue = 0,
        count = 0;
    for (let y = 0; y < height; y += step) {
        for (let x = 0; x < width; x += step) {
            const at = (y * width + x) * 4;
            if (Math.abs(lightOf(data, at) - paper) > 3) continue;
            red += data[at]!;
            green += data[at + 1]!;
            blue += data[at + 2]!;
            count++;
        }
    }
    return count
        ? [
              Math.round(red / count),
              Math.round(green / count),
              Math.round(blue / count),
          ]
        : [255, 255, 255];
}

/**
 * How sharp the lines are when the dots of ink are looked at from this angle: each dot falls in the row it would
 * be in if the side were turned back by `degrees`, and the rows are the fuller, and the fewer, the straighter the
 * lines of text lie along them.
 */
function sharpness(
    xs: Float32Array,
    ys: Float32Array,
    degrees: number,
    size: number,
): number {
    const angle = (degrees * Math.PI) / 180;
    const cos = Math.cos(angle),
        sin = Math.sin(angle);
    const rows = new Uint32Array(size);
    const half = size / 2;
    for (let index = 0; index < xs.length; index++)
        rows[Math.round(ys[index]! * cos - xs[index]! * sin + half)]!++;
    let total = 0;
    for (const row of rows) total += row * row;
    return total;
}

/** The angle at which the lines are sharpest, and how much sharper than the average they are there. */
function sharpest(
    xs: Float32Array,
    ys: Float32Array,
    size: number,
): { angle: number; sure: number } {
    let best = 0,
        bestScore = 0,
        sum = 0,
        count = 0;
    for (let tenths = -SKEW_MOST * 10; tenths <= SKEW_MOST * 10; tenths += 5) {
        const score = sharpness(xs, ys, tenths / 10, size);
        sum += score;
        count++;
        if (score > bestScore) [best, bestScore] = [tenths, score];
    }
    // around the best of the half degrees, tenth by tenth
    const coarse = best;
    for (let tenths = coarse - 4; tenths <= coarse + 4; tenths++) {
        const score = sharpness(xs, ys, tenths / 10, size);
        if (score > bestScore) [best, bestScore] = [tenths, score];
    }
    return { angle: best / 10, sure: sum ? bestScore / (sum / count) : 0 };
}

/**
 * By how much the sheet is turned in the picture, in degrees clockwise, to a tenth: `turnBy` with the opposite
 * straightens it. Null when it cannot be told (a blank side, a photo, a sheet more crooked than `SKEW_MOST`).
 *
 * The lines of a text are found by trying the angles one by one: at the right one the dots of ink gather in few
 * rows, at the others they spread over all of them. A side scanned lying down has its lines along the columns,
 * so both are tried and the sharper one is believed.
 */
export function skewOf(image: Pixels): number | null {
    const { data, width, height } = image;
    const left = Math.round(width * SKEW_BORDER),
        top = Math.round(height * SKEW_BORDER);
    const right = width - left,
        bottom = height - top;
    if (right - left < 20 || bottom - top < 20) return null;

    const counts = Array.from<number>({ length: 256 }).fill(0);
    for (let y = top; y < bottom; y++) {
        for (let x = left; x < right; x++)
            counts[lightOf(data, (y * width + x) * 4)]!++;
    }
    const ink = counts.indexOf(Math.max(...counts)) - INK_DARKER;
    let dots = 0;
    for (let level = 0; level < ink; level++) dots += counts[level]!;
    if (dots < SKEW_FEWEST) return null;

    const every = Math.ceil(dots / SKEW_POINTS);
    const xs = new Float32Array(Math.ceil(dots / every)),
        ys = new Float32Array(xs.length);
    let seen = 0,
        kept = 0;
    for (let y = top; y < bottom; y++) {
        for (let x = left; x < right; x++) {
            if (lightOf(data, (y * width + x) * 4) >= ink || seen++ % every)
                continue;
            xs[kept] = x - width / 2;
            ys[kept] = y - height / 2;
            kept++;
        }
    }

    const size = Math.ceil(Math.hypot(width, height)) + 2;
    const across = sharpest(xs, ys, size);
    // the same search with the picture on its side: the columns in the place of the rows
    const down = sharpest(
        ys.map((y) => -y),
        xs,
        size,
    );
    const found = across.sure >= down.sure ? across : down;
    if (found.sure < SKEW_SURE || Math.abs(found.angle) >= SKEW_MOST)
        return null;
    return found.angle;
}

/**
 * The same picture turned clockwise by so many degrees around its centre, with the size it had: what goes out of
 * the corners is lost, and what comes in takes the colour of the paper. For the few degrees of a crooked sheet;
 * the quarters of a turn are written in the PDF and move no dot.
 */
export function turnBy(
    image: Pixels,
    degrees: number,
    paper: Colour = paperOf(image),
): Pixels {
    const { data, width, height } = image;
    const angle = (degrees * Math.PI) / 180;
    const cos = Math.cos(angle),
        sin = Math.sin(angle);
    const cx = width / 2,
        cy = height / 2;
    const out = new Uint8ClampedArray(data.length);

    for (let row = 0; row < height; row++) {
        const dy = row + 0.5 - cy;
        // the point of the picture the first dot of the row comes from; each dot after it is one step along
        let x = cos * (0.5 - cx) + sin * dy + cx - 0.5;
        let y = -sin * (0.5 - cx) + cos * dy + cy - 0.5;
        for (let column = 0; column < width; column++, x += cos, y -= sin) {
            const at = (row * width + column) * 4;
            out[at + 3] = 255;
            if (x < -0.5 || y < -0.5 || x > width - 0.5 || y > height - 0.5) {
                out[at] = paper[0];
                out[at + 1] = paper[1];
                out[at + 2] = paper[2];
                continue;
            }
            // between four dots of the picture
            const px = Math.min(width - 1, Math.max(0, x)),
                py = Math.min(height - 1, Math.max(0, y));
            const x0 = Math.floor(px),
                y0 = Math.floor(py);
            const x1 = Math.min(width - 1, x0 + 1),
                y1 = Math.min(height - 1, y0 + 1);
            const fx = px - x0,
                fy = py - y0;
            const i00 = (y0 * width + x0) * 4,
                i10 = (y0 * width + x1) * 4,
                i01 = (y1 * width + x0) * 4,
                i11 = (y1 * width + x1) * 4;
            for (let channel = 0; channel < 3; channel++) {
                const above =
                    data[i00 + channel]! +
                    (data[i10 + channel]! - data[i00 + channel]!) * fx;
                const below =
                    data[i01 + channel]! +
                    (data[i11 + channel]! - data[i01 + channel]!) * fx;
                out[at + channel] = above + (below - above) * fy;
            }
        }
    }
    return { data: out, width, height };
}

/**
 * The same picture with the light evened out and more contrast: the paper goes to white wherever it is, and the
 * ink gets darker. The colours stay.
 *
 * The light is measured square by square: in each one the brightest tenth of the dots is paper (the ink is
 * darker), and that brightness is what the square is divided by.
 *
 * `strength` goes from 0 to `MOST_STRENGTH`. Up to 1 it is how far each dot goes from what it was to what it
 * becomes: at a half the paper is halfway to white and the ink halfway to black. Past 1 every dot goes all the way,
 * and the black and the white move towards each other (`BLACK_MOST`, `WHITE_LEAST`): more contrast.
 */
export function enhance(image: Pixels, strength = 1): Pixels {
    const { data, width, height } = image;
    const cell = Math.max(8, Math.round(Math.max(width, height) / 40));
    const columns = Math.ceil(width / cell),
        rows = Math.ceil(height / cell);

    let light = new Float32Array(columns * rows);
    const histogram = new Uint32Array(256);
    for (let gy = 0; gy < rows; gy++) {
        for (let gx = 0; gx < columns; gx++) {
            histogram.fill(0);
            let count = 0;
            const endY = Math.min(height, (gy + 1) * cell),
                endX = Math.min(width, (gx + 1) * cell);
            for (let y = gy * cell; y < endY; y += 2) {
                for (let x = gx * cell; x < endX; x += 2) {
                    histogram[lightOf(data, (y * width + x) * 4)]!++;
                    count++;
                }
            }
            let above = 0,
                level = 255;
            while (level > 0 && (above += histogram[level]!) < count * 0.1)
                level--;
            light[gy * columns + gx] = level;
        }
    }

    // a square with no paper in it (a photo, a filled box) takes the light of its neighbours, not its own
    const sorted = Float32Array.from(light).sort();
    const floor = (sorted[sorted.length >> 1]! / MAX_GAIN) * 1.6;
    for (let i = 0; i < light.length; i++)
        light[i] = Math.max(light[i]!, floor);

    // smoothed twice, so the squares do not show
    for (let pass = 0; pass < 2; pass++) {
        const smooth = new Float32Array(light.length);
        for (let gy = 0; gy < rows; gy++) {
            for (let gx = 0; gx < columns; gx++) {
                let sum = 0,
                    count = 0;
                for (let dy = -1; dy <= 1; dy++) {
                    for (let dx = -1; dx <= 1; dx++) {
                        const x = gx + dx,
                            y = gy + dy;
                        if (x < 0 || y < 0 || x >= columns || y >= rows)
                            continue;
                        sum += light[y * columns + x]!;
                        count++;
                    }
                }
                smooth[gy * columns + gx] = sum / count;
            }
        }
        light = smooth;
    }

    const out = new Uint8ClampedArray(data.length);
    const mix = Math.min(1, Math.max(0, strength));
    const over = Math.min(MOST_STRENGTH, Math.max(1, strength)) - 1;
    const black = BLACK + over * (BLACK_MOST - BLACK);
    const stretch = 255 / (255 - over * (255 - WHITE_LEAST) - black);
    for (let y = 0; y < height; y++) {
        const gy = Math.min(rows - 1, Math.max(0, (y + 0.5) / cell - 0.5));
        const y0 = Math.floor(gy),
            y1 = Math.min(rows - 1, y0 + 1),
            fy = gy - y0;
        for (let x = 0; x < width; x++) {
            const gx = Math.min(
                columns - 1,
                Math.max(0, (x + 0.5) / cell - 0.5),
            );
            const x0 = Math.floor(gx),
                x1 = Math.min(columns - 1, x0 + 1),
                fx = gx - x0;
            const above =
                light[y0 * columns + x0]! +
                (light[y0 * columns + x1]! - light[y0 * columns + x0]!) * fx;
            const below =
                light[y1 * columns + x0]! +
                (light[y1 * columns + x1]! - light[y1 * columns + x0]!) * fx;
            const gain = Math.min(
                MAX_GAIN,
                PAPER / Math.max(1, above + (below - above) * fy),
            );
            const i = (y * width + x) * 4;
            for (let channel = 0; channel < 3; channel++) {
                const was = data[i + channel]!;
                // within 0 and 255 before the two are mixed: a dot burnt to white is white, not whiter
                const full = Math.min(
                    255,
                    Math.max(0, (was * gain - black) * stretch),
                );
                out[i + channel] = was + (full - was) * mix;
            }
            out[i + 3] = 255;
        }
    }
    return { data: out, width, height };
}

/** Black and white: the same picture in shades of grey, for a document the colours are seldom needed. */
export function grey(image: Pixels): Pixels {
    const { data, width, height } = image;
    const out = new Uint8ClampedArray(data.length);
    for (let i = 0; i < data.length; i += 4) {
        const light = lightOf(data, i);
        out[i] = light;
        out[i + 1] = light;
        out[i + 2] = light;
        out[i + 3] = 255;
    }
    return { data: out, width, height };
}

/**
 * A picture with what was asked for it: first the light is evened out, then the colours go.
 *
 * @param image the picture
 * @param options which of Improve (`enhance`, with its `strength`, 1 by default) and Black and white (`grey`) to apply
 * @returns the same picture when nothing is asked, otherwise a new one
 */
export function improve(
    image: Pixels,
    options: { enhance: boolean; grey: boolean; strength?: number },
): Pixels {
    let picture = image;
    if (options.enhance) picture = enhance(picture, options.strength ?? 1);
    if (options.grey) picture = grey(picture);
    return picture;
}
