/**
 * How a file is shown in its preview.
 */

/**
 * The image the server made of the file (`preview`), the file itself when it
 * is a JPEG or a PNG (`image`), the placeholder for the rest (`other`).
 */
export type PreviewKind = 'preview' | 'image' | 'other';

/** The types a document takes, by extension and by type, for the file picker. */
export const DOCUMENT_FILE_TYPES =
    '.pdf,.jpg,.jpeg,.png,.heic,application/pdf,image/jpeg,image/png,image/heic';

/** The types of a photo or a logo, as the server takes them (`ImageUpload::RULES`). */
export const IMAGE_FILE_TYPES =
    '.jpg,.jpeg,.png,.webp,.heic,image/jpeg,image/png,image/webp,image/heic';

/** The image types every browser opens: HEIC is not among them. */
const IMAGES = ['image/jpeg', 'image/png'];

/**
 * How to show a file.
 *
 * @param mimeType - the real type of the file, for example `application/pdf`
 * @param previewUrl - the address of the preview image made by the server, or `null` when the file has none
 * @returns the kind of preview
 */
export function previewKind(
    mimeType: string,
    previewUrl: string | null,
): PreviewKind {
    if (previewUrl !== null) {
        return 'preview';
    }

    return IMAGES.includes(mimeType) ? 'image' : 'other';
}
