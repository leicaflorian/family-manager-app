/**
 * How a file is shown in its preview.
 */

/** A PDF in the page, an image as it is, an icon for the rest. */
export type PreviewKind = 'pdf' | 'image' | 'other';

/** The image types every browser opens: HEIC is not among them. */
const IMAGES = ['image/jpeg', 'image/png'];

/**
 * How to show a file of the given type.
 *
 * @param mimeType - the real type of the file, for example `application/pdf`
 * @returns the kind of preview
 */
export function previewKind(mimeType: string): PreviewKind {
    if (mimeType === 'application/pdf') {
        return 'pdf';
    }

    return IMAGES.includes(mimeType) ? 'image' : 'other';
}
