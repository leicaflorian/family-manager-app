// Tells a blank side from a written one: the back of a sheet written only on the front. It is decided in the page
// and not in the agent, so the thresholds are corrected by rebuilding the extension, without a new agent.

import type { Pixels } from './crop';

/** The side is looked at this small: enough to see a line of text, little enough to be quick. */
const WIDTH = 200;

/** The part of each edge left out: the shadow of the sheet and the holes of a binder. */
const BORDER = 0.05;

/** How much darker than the paper a dot must be to count as ink (0-255): the writing of the other side, showing through, is lighter. */
const INK_DARKER = 30;

/**
 * Below this share of dots of ink the side is blank. A side with one short line ("Pagina 2 di 2") has about 0.1%:
 * when in doubt the side is kept, since a blank left among the others is taken away with a click, and a written
 * one put aside could go unnoticed. To be corrected with the real sheets.
 */
export const BLANK_SHARE = 0.0005;

/**
 * The share of the dots of a picture that hold ink, from 0 to 1, the edges left out. The picture is already small
 * (`WIDTH` dots across), see `inkShare`.
 */
export function inkShareOf(picture: Pixels): number {
    const left = Math.round(picture.width * BORDER);
    const top = Math.round(picture.height * BORDER);
    const width = picture.width - left * 2;
    const height = picture.height - top * 2;
    const data = new Uint8ClampedArray(width * height * 4);
    for (let row = 0; row < height; row++) {
        const from = ((row + top) * picture.width + left) * 4;
        data.set(
            picture.data.subarray(from, from + width * 4),
            row * width * 4,
        );
    }

    // the colour of the paper is the most common lightness: a sheet is not always white
    const counts = Array.from<number>({ length: 256 }).fill(0);
    const lightness = new Uint8Array(data.length / 4);
    for (let index = 0; index < lightness.length; index++) {
        const value = Math.round(
            0.299 * data[index * 4]! +
                0.587 * data[index * 4 + 1]! +
                0.114 * data[index * 4 + 2]!,
        );
        lightness[index] = value;
        counts[value]!++;
    }
    const paper = counts.indexOf(Math.max(...counts));

    let ink = 0;
    for (const value of lightness) if (value < paper - INK_DARKER) ink++;
    return ink / lightness.length;
}

/** The share of the dots of a side that hold ink, from 0 to 1; 1 when the browser cannot draw it. */
export function inkShare(
    image: CanvasImageSource,
    width: number,
    height: number,
): number {
    const canvas = document.createElement('canvas');
    canvas.width = WIDTH;
    canvas.height = Math.max(1, Math.round((height / width) * WIDTH));
    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (!context) return 1;
    context.imageSmoothingQuality = 'high';
    context.drawImage(image, 0, 0, canvas.width, canvas.height);
    return inkShareOf(context.getImageData(0, 0, canvas.width, canvas.height));
}

/** True when the side is blank: below `BLANK_SHARE` of ink. */
export function isBlank(
    image: CanvasImageSource,
    width: number,
    height: number,
): boolean {
    return inkShare(image, width, height) < BLANK_SHARE;
}

/** True when the small picture of a side is blank (`isBlank`, on pixels already read). */
export function isBlankPixels(picture: Pixels): boolean {
    return inkShareOf(picture) < BLANK_SHARE;
}
