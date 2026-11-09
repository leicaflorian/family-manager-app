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
import { improve } from './improve';
import type { Backend, PageImage, ScanPage, SheetPlan } from './pages';

/** The quality of the JPEGs the scan makes. */
const JPEG_QUALITY = 0.85;

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
    canvas.width = image.width;
    canvas.height = image.height;
    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('No canvas');
    }

    context.putImageData(
        new ImageData(
            new Uint8ClampedArray(image.data),
            image.width,
            image.height,
        ),
        0,
        0,
    );
    const blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', quality),
    );
    // the phone gives the memory of a canvas back only when it is emptied
    canvas.width = 0;
    canvas.height = 0;

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
 * @returns the finished side (`jpeg`) and the side before the improvements (`plain`), shown while it is held
 * @throws if the browser cannot draw the photo
 */
export async function cutOut(
    file: Blob,
    corners: Corners,
    turns: Turns,
    options: { enhance: boolean; grey: boolean },
): Promise<{ jpeg: Blob; plain: Blob }> {
    const sheet = warp(
        await readPhoto(file, SOURCE_LONG_SIDE),
        sourceCorners(corners, turns),
        LONG_SIDE,
    );
    const plain = await toJpeg(sheet);
    const finished = improve(sheet, options);

    return {
        jpeg: finished === sheet ? plain : await toJpeg(finished),
        plain,
    };
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

            const blob = await new Promise<Blob | null>((resolve) =>
                canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY),
            );

            if (!blob) {
                throw new Error('No JPEG');
            }

            return await describe(blob);
        } finally {
            canvas.width = 0;
            canvas.height = 0;
        }
    },
};
