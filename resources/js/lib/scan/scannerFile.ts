// The PDF of the sides of the scanner that stay: one file, a page for each side in the order of the strip, or with
// "On a single page" the sides on one A4 page. The same rules as the photos of the phone (`filesToSend`), minus
// the single JPEG: the scanner always makes a PDF.

import { trans } from 'laravel-vue-i18n';
import { canvasBackend } from './canvas';
import type { Backend, PageImage, ScanPage } from './pages';
import {
    MAX_UPLOAD_BYTES,
    ScanTooHeavyError,
    oneSheetState,
    sheetPlan,
} from './pages';
import { makePdf } from './pdf';

/**
 * The page of the phone that stands for a side of the scanner: a cut out photo, which `Backend.load` takes as it is.
 *
 * @param blob - the finished JPEG of the side
 * @param index - its place
 * @returns the page
 */
function pageOf(blob: Blob, index: number): ScanPage {
    return {
        id: `side-${index}`,
        file: new File([blob], `side-${index}.jpg`, { type: 'image/jpeg' }),
        kind: 'photo',
        corners: null,
        turns: 0,
        improve: { enhance: false, grey: false, dewarp: false },
        cut: true,
        jpeg: blob,
    };
}

/**
 * The PDF that leaves for *To sort*.
 *
 * @param sides - the sides that stay, each with its finished JPEG, in order
 * @param options - `oneSheet`: "On a single page" is switched on (it counts only for two to four sides); `backend`:
 *   what needs a canvas, the browser's by default
 * @returns the PDF
 * @throws ScanTooHeavyError when it is over `MAX_UPLOAD_BYTES`
 */
export async function scannerFile(
    sides: { blob: Blob }[],
    options: { oneSheet: boolean; backend?: Backend },
): Promise<File> {
    const backend = options.backend ?? canvasBackend;
    const pages = sides.map((side, index) => pageOf(side.blob, index));
    const images: PageImage[] = [];

    // one at a time: a side at 300 dots takes tens of MB while it is read
    for (const page of pages) {
        images.push(await backend.load(page));
    }

    const single = options.oneSheet && oneSheetState(pages) === 'ready';
    const sheet = single
        ? await backend.sheet(images, sheetPlan(images))
        : null;
    const file = new File([makePdf(sheet ? [sheet] : images)], 'scan.pdf', {
        type: 'application/pdf',
    });

    if (file.size > MAX_UPLOAD_BYTES) {
        throw new ScanTooHeavyError(
            single
                ? trans('The PDF of the page')
                : trans('The PDF of the pages'),
            file.size,
        );
    }

    return file;
}
