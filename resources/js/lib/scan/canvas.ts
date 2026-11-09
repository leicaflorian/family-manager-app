/**
 * The browser side of the scan libraries: a photo from a file to `Pixels`, and
 * `Pixels` to a picture the page can show. It needs a real canvas, so jsdom does
 * not run it and the pages that use it mock it in their tests.
 */
import { LONG_SIDE, type Pixels } from './crop';

/**
 * Reads a photo the way the phone shows it (the turn of the camera applied) and
 * brings its long side down to `LONG_SIDE`.
 *
 * @param file - a picture chosen by the person
 * @returns the pixels of the photo
 * @throws if the browser cannot draw the file
 */
export async function readPhoto(file: File): Promise<Pixels> {
    const bitmap = await createImageBitmap(file, {
        imageOrientation: 'from-image',
    });
    const scale = Math.min(
        1,
        LONG_SIDE / Math.max(bitmap.width, bitmap.height),
    );
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

    return {
        width,
        height,
        data: context.getImageData(0, 0, width, height).data,
    };
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
        canvas.toBlob(resolve, 'image/jpeg', 0.85),
    );

    if (!blob) {
        throw new Error('No JPEG');
    }

    return URL.createObjectURL(blob);
}
