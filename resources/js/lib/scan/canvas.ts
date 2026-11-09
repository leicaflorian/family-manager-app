/**
 * The browser side of the scan libraries: a photo from a file to `Pixels`, and
 * `Pixels` to a picture the page can show. It needs a real canvas, so jsdom does
 * not run it and the pages that use it mock it in their tests.
 */
import {
    LONG_SIDE,
    SOURCE_LONG_SIDE,
    sourceCorners,
    turn,
    warp,
    type Corners,
    type Pixels,
    type Turns,
} from './crop';
import {
    densityOf,
    findCard,
    improveCanvas,
    drawOn,
    millimetreOf,
    scaleCut,
    sheetCanvas,
} from './card';
import type { Card, Cut } from './card';
import {
    improve,
    improveSide,
    isPlain,
    SKEW_LONG_SIDE,
    skewOf,
} from './improve';
import type { Improvement } from './improve';
import type { Backend, PageImage, ScanPage, SheetPlan } from './pages';

/** The quality of the JPEGs the scan makes. */
const JPEG_QUALITY = 0.85;

/** The quality of the JPEG of a composed page: mostly white, so it stays light even this high. */
const SHEET_JPEG_QUALITY = 0.9;

/** The long side of the copy of a photo the server looks for the corners in. */
export const SEARCH_LONG_SIDE = 1000;

/**
 * Reads a photo the way the phone shows it (the turn of the camera applied) and
 * brings its long side down to `longSide`.
 *
 * @param file - a picture chosen by the person
 * @param longSide - the longest side wanted, `LONG_SIDE` by default
 * @returns the pixels of the photo
 * @throws if the browser cannot draw the file
 */
export async function readPhoto(
    file: Blob,
    longSide = LONG_SIDE,
): Promise<Pixels> {
    const bitmap = await createImageBitmap(file, {
        imageOrientation: 'from-image',
    });
    const scale = Math.min(1, longSide / Math.max(bitmap.width, bitmap.height));
    const width = Math.max(1, Math.round(bitmap.width * scale));
    const height = Math.max(1, Math.round(bitmap.height * scale));
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d', { willReadFrequently: true });

    if (!context) {
        throw new Error('No canvas');
    }

    context.drawImage(bitmap, 0, 0, width, height);
    bitmap.close();
    const { data } = context.getImageData(0, 0, width, height);
    // a phone has little canvas memory: this one is given back now, not when the browser gets round to it
    canvas.width = 0;
    canvas.height = 0;

    return { width, height, data };
}

/**
 * A JPEG of some pixels.
 *
 * @param image - the pixels
 * @param quality - from 0 to 1
 * @returns the JPEG
 * @throws if the browser cannot make the JPEG
 */
export async function toJpeg(
    image: Pixels,
    quality = JPEG_QUALITY,
): Promise<Blob> {
    const canvas = document.createElement('canvas');

    try {
        drawOn(canvas, image);

        return await jpegOfCanvas(canvas, quality);
    } finally {
        // the phone gives the memory of a canvas back only when it is emptied
        canvas.width = 0;
        canvas.height = 0;
    }
}

/**
 * The JPEG of a canvas as it is drawn.
 *
 * @param canvas - the canvas
 * @param quality - from 0 to 1
 * @returns the JPEG
 * @throws if the browser cannot make the JPEG
 */
export async function jpegOfCanvas(
    canvas: HTMLCanvasElement,
    quality = JPEG_QUALITY,
): Promise<Blob> {
    const blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', quality),
    );

    if (!blob) {
        throw new Error('No JPEG');
    }

    return blob;
}

/**
 * A JPEG address for some pixels, to show them in an `<img>`. The caller
 * revokes it with `URL.revokeObjectURL` when it is done.
 *
 * @param image - the pixels
 * @returns an object URL
 * @throws if the browser cannot make the JPEG
 */
export async function toObjectUrl(image: Pixels): Promise<string> {
    return URL.createObjectURL(await toJpeg(image));
}

/**
 * A photo brought down to a long side, as a JPEG: the photo the page shows and crops (2000 px, quality 0.85),
 * or the small copy the corners are looked for in (1000 px).
 *
 * @param file - the photo
 * @param longSide - the longest side wanted
 * @param quality - from 0 to 1
 * @returns the JPEG
 * @throws if the browser cannot draw the photo
 */
export async function reduceToJpeg(
    file: Blob,
    longSide = LONG_SIDE,
    quality = JPEG_QUALITY,
): Promise<Blob> {
    return toJpeg(await readPhoto(file, longSide), quality);
}

/**
 * Cuts the sheet out of the photo as it was taken (up to `SOURCE_LONG_SIDE`, for the most detail), with the
 * corners placed on the photo as it is shown, and applies *Improve* and *Black and white*.
 *
 * @param file - the photo as the phone gave it
 * @param corners - the corners on the photo turned by `turns`
 * @param turns - quarters of a turn the photo is shown with
 * @param options - which improvements to apply
 * @param straighten - *Straighten*: lays the cut sheet flat before the improvements (never the whole photo); absent when off
 * @returns the finished side (`jpeg`) and the side before the improvements (`plain`), shown while it is held
 * @throws if the browser cannot draw the photo, or `straighten` fails
 */
export async function cutOut(
    file: Blob,
    corners: Corners,
    turns: Turns,
    options: { enhance: boolean; grey: boolean },
    straighten?: (sheet: Pixels) => Promise<Pixels>,
): Promise<{ jpeg: Blob; plain: Blob }> {
    const cut = warp(
        await readPhoto(file, SOURCE_LONG_SIDE),
        sourceCorners(corners, turns),
        LONG_SIDE,
    );
    const sheet = straighten ? await straighten(cut) : cut;
    const plain = await toJpeg(sheet);
    const finished = improve(sheet, options);

    return {
        jpeg: finished === sheet ? plain : await toJpeg(finished),
        plain,
    };
}

/** What the page learns of a side of the scanner when it arrives. */
export type SideMeasure = {
    /** How many of its dots make a millimetre. */
    millimetre: number;
    /** By how much the sheet went in crooked, in degrees clockwise; null when the side does not say. */
    skew: number | null;
    /** Where a card was found on it, in its dots; null when none was. */
    found: Cut | null;
    /** What was found is small enough to be a card, not a sheet. */
    small: boolean;
};

/**
 * The dots of a picture, as large as it is or brought within `longSide`.
 *
 * @param image - the picture
 * @param longSide - the longest side wanted
 * @returns the pixels
 * @throws if the browser has no canvas for it
 */
export function pixelsOf(image: ImageBitmap, longSide = Infinity): Pixels {
    const scale = Math.min(1, longSide / Math.max(image.width, image.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(image.width * scale));
    canvas.height = Math.max(1, Math.round(image.height * scale));
    const context = canvas.getContext('2d', { willReadFrequently: true });

    if (!context) {
        throw new Error('No canvas');
    }

    context.imageSmoothingQuality = 'high';
    context.drawImage(image, 0, 0, canvas.width, canvas.height);
    const { width, height, data } = context.getImageData(
        0,
        0,
        canvas.width,
        canvas.height,
    );
    canvas.width = 0;
    canvas.height = 0;

    return { width, height, data };
}

/**
 * Measures a side of the scanner as it arrives: the size of a dot (from its JPEG, else from the resolution asked),
 * how crooked it went in, and where a card lies on it. What cannot be measured is left to the hands.
 *
 * @param blob - the JPEG of the scanner
 * @param bitmap - the same side, already decoded
 * @param asked - the dots per inch asked of the scanner, null when unknown
 * @returns what was measured
 */
export async function measureSide(
    blob: Blob,
    bitmap: ImageBitmap,
    asked: number | null,
): Promise<SideMeasure> {
    const millimetre = millimetreOf(
        bitmap,
        await densityOf(blob).catch(() => null),
        asked,
    );
    const measure: SideMeasure = {
        millimetre,
        skew: null,
        found: null,
        small: false,
    };

    try {
        const pixels = pixelsOf(bitmap, SKEW_LONG_SIDE);
        measure.skew = skewOf(pixels);
        const seen = findCard(
            pixels,
            (millimetre * pixels.width) / bitmap.width,
        );

        if (seen) {
            measure.found = scaleCut(seen.cut, bitmap.width / pixels.width);
            measure.small = seen.small;
        }
    } catch {
        // not measured: the side can still be turned, and its cut set, by hand
    }

    return measure;
}

/**
 * The JPEG of a side of the scanner with what was asked for it, as large as the side is. It starts from the JPEG of
 * the scanner every time, so a side is compressed at most twice; with nothing asked it is that JPEG.
 *
 * @param original - the JPEG of the scanner
 * @param improvement - the angle, *Improve* and grey asked
 * @returns the JPEG
 * @throws if the browser cannot draw the side
 */
export async function improvedJpeg(
    original: Blob,
    improvement: Improvement,
): Promise<Blob> {
    if (isPlain(improvement)) {
        return original;
    }

    const bitmap = await createImageBitmap(original);
    let pixels: Pixels;

    try {
        pixels = pixelsOf(bitmap);
    } finally {
        bitmap.close();
    }

    return toJpeg(improveSide(pixels, improvement));
}

/**
 * A composed page: the cards cut from their sides onto an A4 at their real size, then *Improve* done on the page.
 *
 * @param cards - the cards, from the top
 * @param improvement - what *Improve* asks of the page
 * @returns the page before *Improve* (`original`), the one that goes in the PDF (`blob`) and its size in dots
 * @throws if the browser cannot draw the page
 */
export async function composedSheet(
    cards: Card[],
    improvement: Improvement,
): Promise<{ original: Blob; blob: Blob; width: number; height: number }> {
    const canvas = await sheetCanvas(cards);
    const { width, height } = canvas;

    try {
        const original = await jpegOfCanvas(canvas, SHEET_JPEG_QUALITY);

        if (isPlain(improvement)) {
            return { original, blob: original, width, height };
        }

        improveCanvas(canvas, improvement);

        return {
            original,
            blob: await jpegOfCanvas(canvas, SHEET_JPEG_QUALITY),
            width,
            height,
        };
    } finally {
        canvas.width = 0;
        canvas.height = 0;
    }
}

/** The bytes and the size of a JPEG. */
async function describe(blob: Blob): Promise<PageImage> {
    const bitmap = await createImageBitmap(blob);
    const { width, height } = bitmap;
    bitmap.close();

    return {
        jpeg: new Uint8Array(await blob.arrayBuffer()),
        width,
        height,
        // a canvas always writes three channels, grey or not
        grey: false,
    };
}

/** Draws a path of round corners. */
function roundedBox(
    context: CanvasRenderingContext2D,
    x: number,
    y: number,
    width: number,
    height: number,
    radius: number,
): void {
    context.beginPath();
    context.moveTo(x + radius, y);
    context.arcTo(x + width, y, x + width, y + height, radius);
    context.arcTo(x + width, y + height, x, y + height, radius);
    context.arcTo(x, y + height, x, y, radius);
    context.arcTo(x, y, x + width, y, radius);
    context.closePath();
}

/**
 * The browser's canvas for `filesToSend`: a cut out page is its JPEG as it is; a photo not cut out is read as
 * the phone shows it, given its turns and written again (a PDF viewer does not read the turn of a camera).
 */
export const canvasBackend: Backend = {
    async load(page: ScanPage): Promise<PageImage> {
        if (page.jpeg) {
            return describe(page.jpeg);
        }

        return describe(
            await toJpeg(turn(await readPhoto(page.file), page.turns)),
        );
    },

    async sheet(images: PageImage[], plan: SheetPlan): Promise<PageImage> {
        const canvas = document.createElement('canvas');
        canvas.width = plan.width;
        canvas.height = plan.height;

        try {
            const context = canvas.getContext('2d');

            if (!context) {
                throw new Error('No canvas');
            }

            // the paper is white in both themes: it is a sheet to print, not part of the page
            context.fillStyle = 'white';
            context.fillRect(0, 0, plan.width, plan.height);
            context.imageSmoothingQuality = 'high';

            for (const [index, image] of images.entries()) {
                const place = plan.places[index];
                const bitmap = await createImageBitmap(
                    new Blob([image.jpeg as BlobPart], { type: 'image/jpeg' }),
                );
                context.save();

                if (plan.rounded[index]) {
                    // the table left at the four corners of a card, which are round, is cut away
                    roundedBox(
                        context,
                        place.x,
                        place.y,
                        place.width,
                        place.height,
                        Math.min(
                            plan.radius,
                            place.width / 2,
                            place.height / 2,
                        ),
                    );
                    context.clip();
                }

                context.drawImage(
                    bitmap,
                    place.x,
                    place.y,
                    place.width,
                    place.height,
                );
                context.restore();
                bitmap.close();
            }

            return await describe(await jpegOfCanvas(canvas));
        } finally {
            canvas.width = 0;
            canvas.height = 0;
        }
    },
};
