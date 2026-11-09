// The PDF of some sides of the scanner: a page for each, in the order given. Each JPEG goes in as the scanner, or
// *Improve*, made it: a turn is written as `/Rotate` and a side scanned in grey as `/DeviceGray`, read from its
// JPEG, so nothing is decoded or compressed again here.

import { trans } from 'laravel-vue-i18n';
import { checkWeight } from './pages';
import { jpegColours, makePdf } from './pdf';
import type { Rotation } from './pdf';

/** A side of the scanner as it goes in the PDF: its JPEG, its size in dots, and how much it is turned. */
export type ScannerPage = {
    blob: Blob;
    width: number;
    height: number;
    rotation: Rotation;
};

/**
 * The PDF of some sides.
 *
 * @param pages - the sides, in order
 * @returns the PDF
 * @throws ScanTooHeavyError when it is over `MAX_UPLOAD_BYTES`
 */
export async function scannerFile(pages: ScannerPage[]): Promise<File> {
    const parts = [];

    // one at a time: a side at 300 dots weighs some MB
    for (const page of pages) {
        const jpeg = new Uint8Array(await page.blob.arrayBuffer());
        parts.push({
            jpeg,
            width: page.width,
            height: page.height,
            grey: jpegColours(jpeg) === 1,
            rotate: page.rotation,
        });
    }

    return checkWeight(
        new File([makePdf(parts)], 'scan.pdf', { type: 'application/pdf' }),
        pages.length === 1
            ? trans('The PDF of the page')
            : trans('The PDF of the pages'),
    );
}
