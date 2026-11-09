/**
 * The pages of a document in the scan: the list with the one chosen, how many there are, "On a single page", and
 * the files that leave for the server. The logic is plain; what needs a canvas (reading a photo, drawing the A4
 * sheet) sits behind `Backend`, which `canvas.ts` fills in the browser and the tests fill with a fake.
 */
import { transChoice, trans } from 'laravel-vue-i18n';
import { canvasBackend } from './canvas';
import {
    CARD_CORNER,
    SHEET,
    sheetLayout,
    sideSize,
    type Corners,
    type Place,
    type Turns,
} from './crop';
import { makePdf, type PdfPage } from './pdf';

/** The most one file may weigh when it leaves for the server: 25 MB. */
export const MAX_UPLOAD_BYTES = 26214400;
/** The fewest and the most photos "On a single page" takes. */
export const SHEET_PHOTOS = { least: 2, most: 4 } as const;
/** The dots per inch of the A4 sheet: the ones a scanner is asked for a card. */
export const SHEET_DENSITY = 300;

/** One page of the document: a photo with what was done to it, or a PDF that goes as it is. */
export type ScanPage = {
    id: string;
    file: File;
    kind: 'photo' | 'pdf';
    /** Where the sheet is on the photo, from 0 to 1, or null while it was not found or set. */
    corners: Corners | null;
    turns: Turns;
    improve: { enhance: boolean; grey: boolean; dewarp: boolean };
    /** The photo has been cut out: `jpeg` is the finished side, with the turns and the improvements in it. */
    cut: boolean;
    jpeg: Blob | null;
};

/** The pages and the one in the large view; the page can keep more about each page (`P`). */
export type ScanDocument<P extends ScanPage = ScanPage> = {
    pages: P[];
    chosenId: string | null;
};

/** A JPEG ready for a PDF, with its size and whether it has one grey channel. */
export type PageImage = {
    jpeg: Uint8Array;
    width: number;
    height: number;
    grey: boolean;
};

/** Where and how the sides are drawn on the A4 sheet, in pixels. */
export type SheetPlan = {
    width: number;
    height: number;
    places: Place[];
    /** Which sides are cards: their corners are round. */
    rounded: boolean[];
    /** The radius of those corners, in pixels. */
    radius: number;
};

/** The part of the work that needs a canvas. */
export type Backend = {
    /** The finished JPEG of a page: `jpeg` as it is when cut out, otherwise the photo read and turned. */
    load(page: ScanPage): Promise<PageImage>;
    /** Draws the sides on the A4 sheet as the plan says and gives the JPEG of the sheet. */
    sheet(images: PageImage[], plan: SheetPlan): Promise<PageImage>;
};

/** What the person can do with "On a single page". */
export type OneSheetState = 'hidden' | 'blocked' | 'ready';

/** A file is over the limit: the page says which one and by how much. */
export class ScanTooHeavyError extends Error {
    /**
     * @param label - what the file is called for the person
     * @param bytes - what it weighs
     */
    constructor(
        readonly label: string,
        readonly bytes: number,
    ) {
        super(
            trans(':name weighs :size MB: the most is 25 MB.', {
                name: label,
                size: new Intl.NumberFormat(
                    typeof document === 'undefined'
                        ? 'en'
                        : document.documentElement.lang || 'en',
                    { maximumFractionDigits: 1 },
                ).format(bytes / 1048576),
            }),
        );
        this.name = 'ScanTooHeavyError';
    }
}

/**
 * Adds pages at the end; the last one added is the chosen one.
 *
 * @param document - the document
 * @param added - the new pages
 * @returns a new document
 */
export function addPages<P extends ScanPage>(
    document: ScanDocument<P>,
    added: P[],
): ScanDocument<P> {
    return {
        pages: [...document.pages, ...added],
        chosenId: added.at(-1)?.id ?? document.chosenId,
    };
}

/**
 * Takes a page away. If it was the chosen one, the one after it is chosen, or the one before if it was the last.
 *
 * @param document - the document
 * @param id - the page to remove
 * @returns a new document; the same one when the page is not there
 */
export function removePage<P extends ScanPage>(
    document: ScanDocument<P>,
    id: string,
): ScanDocument<P> {
    const index = document.pages.findIndex((page) => page.id === id);

    if (index < 0) {
        return document;
    }

    const pages = document.pages.filter((page) => page.id !== id);
    const chosenId =
        document.chosenId === id
            ? (pages[index]?.id ?? pages[index - 1]?.id ?? null)
            : document.chosenId;

    return { pages, chosenId };
}

/**
 * Puts a page in another place; the choice stays on the same page.
 *
 * @param document - the document
 * @param from - where the page is
 * @param to - where it goes
 * @returns a new document; the same one when a place is outside the list
 */
export function movePage<P extends ScanPage>(
    document: ScanDocument<P>,
    from: number,
    to: number,
): ScanDocument<P> {
    const last = document.pages.length - 1;

    if (from < 0 || to < 0 || from > last || to > last || from === to) {
        return document;
    }

    const pages = [...document.pages];
    pages.splice(to, 0, ...pages.splice(from, 1));

    return { ...document, pages };
}

/**
 * Chooses a page of the document.
 *
 * @param document - the document
 * @param id - the page to show large
 * @returns a new document; the same choice when the page is not there
 */
export function selectPage<P extends ScanPage>(
    document: ScanDocument<P>,
    id: string,
): ScanDocument<P> {
    return document.pages.some((page) => page.id === id)
        ? { ...document, chosenId: id }
        : document;
}

/**
 * How much there is in the document, as its bar says it: «3 pages» for photos, «4 files» with a PDF among them.
 *
 * @param pages - the pages
 * @returns the text, empty without pages
 */
export function pageCount(pages: ScanPage[]): string {
    if (pages.length === 0) {
        return '';
    }

    const photos = pages.filter((page) => page.kind === 'photo').length;

    return photos < pages.length
        ? transChoice('{1} :count file|[2,*] :count files', pages.length, {
              count: String(pages.length),
          })
        : transChoice('{1} :count page|[2,*] :count pages', photos, {
              count: String(photos),
          });
}

/**
 * What leaves with "Upload", as it is written under the small pages: «3 pages, in a single PDF», «1 photo · 1 PDF,
 * uploaded as it is». One photo alone says nothing: it is plainly a photo.
 *
 * @param pages - the pages
 * @param oneSheet - the person switched on "On a single page" (it counts only when it is `ready`)
 * @returns the parts joined by a dot, empty when there is nothing to say
 */
export function sendSummary(pages: ScanPage[], oneSheet: boolean): string {
    const photos = pages.filter((page) => page.kind === 'photo').length;
    const pdfs = pages.filter(
        (page) => page.kind === 'pdf' && isPdfFile(page.file),
    ).length;
    // a photo the browser cannot draw (a HEIC on a computer) also leaves as it is, but it is not a PDF
    const others = pages.length - photos - pdfs;
    const single = oneSheet && oneSheetState(pages) === 'ready';
    const parts: string[] = [];

    if (photos === 1 && pdfs + others > 0) {
        parts.push(trans('1 photo'));
    }

    if (photos > 1) {
        const count = single ? 1 : photos;
        parts.push(
            transChoice(
                '{1} :count page, in a single PDF|[2,*] :count pages, in a single PDF',
                count,
                { count: String(count) },
            ),
        );
    }

    if (pdfs > 0) {
        parts.push(
            transChoice(
                '{1} :count PDF, uploaded as it is|[2,*] :count PDF, uploaded as they are',
                pdfs,
                { count: String(pdfs) },
            ),
        );
    }

    if (others > 0) {
        parts.push(
            transChoice(
                '{1} :count file, uploaded as it is|[2,*] :count files, uploaded as they are',
                others,
                { count: String(others) },
            ),
        );
    }

    return parts.join(' · ');
}

/**
 * Whether a file is a PDF, from its type or, when the phone gives none, its name.
 *
 * @param file - the file
 * @returns true for a PDF
 */
export function isPdfFile(file: File): boolean {
    return file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
}

/**
 * The pages each file of `filesToSend` is made of, in the same order: all the photos in the first, then each PDF
 * in one of its own. A file that reached the server takes its pages out of the document.
 *
 * @param pages - the pages
 * @returns the groups of pages, one a file
 */
export function fileGroups(pages: ScanPage[]): ScanPage[][] {
    const photos = pages.filter((page) => page.kind === 'photo');
    const pdfs = pages.filter((page) => page.kind === 'pdf');

    return [
        ...(photos.length > 0 ? [photos] : []),
        ...pdfs.map((page) => [page]),
    ];
}

/**
 * Whether "On a single page" is offered: for two to four photos, and only when all are cut out.
 *
 * @param pages - the pages
 * @returns `hidden` outside two to four photos, `blocked` while a photo is not cut out, otherwise `ready`
 */
export function oneSheetState(pages: ScanPage[]): OneSheetState {
    const photos = pages.filter((page) => page.kind === 'photo');

    if (
        photos.length < SHEET_PHOTOS.least ||
        photos.length > SHEET_PHOTOS.most
    ) {
        return 'hidden';
    }

    return photos.every((page) => page.cut) ? 'ready' : 'blocked';
}

/**
 * Where the sides go on the A4 sheet: a card at the size of a card, any other side at the size of a passport
 * page, in a column, in pixels at `SHEET_DENSITY`.
 *
 * @param sizes - the sides, in pixels
 * @returns the plan of the sheet
 */
export function sheetPlan(
    sizes: { width: number; height: number }[],
): SheetPlan {
    const millimetre = SHEET_DENSITY / 25.4;
    const sides = sizes.map((size) => sideSize(size.width, size.height));
    const { scale, places } = sheetLayout(sides);

    return {
        width: Math.round(SHEET.width * millimetre),
        height: Math.round(SHEET.height * millimetre),
        places: places.map((place) => ({
            x: place.x * millimetre,
            y: place.y * millimetre,
            width: place.width * millimetre,
            height: place.height * millimetre,
        })),
        rounded: sides.map((side) => side.card),
        radius: CARD_CORNER * scale * millimetre,
    };
}

/** The PDF page of an image. */
const pdfPage = (image: PageImage): PdfPage => ({
    jpeg: image.jpeg,
    width: image.width,
    height: image.height,
    grey: image.grey,
});

/** A file made from some bytes. */
const fileOf = (parts: BlobPart[], name: string, type: string): File =>
    new File(parts, name, { type });

/**
 * What goes to the server: the photos as one file (one photo a JPEG; several a PDF with a page each; with "On a
 * single page" a PDF of one A4 page with all of them), and every PDF as it is, each on its own.
 *
 * @param pages - the pages
 * @param oneSheet - the person switched on "On a single page" (it counts only when it is `ready`)
 * @param backend - what needs a canvas; the browser's by default
 * @returns the files, the photos first
 * @throws ScanTooHeavyError when a file is over `MAX_UPLOAD_BYTES`
 */
export async function filesToSend(
    pages: ScanPage[],
    oneSheet: boolean,
    backend: Backend = canvasBackend,
): Promise<File[]> {
    const photos = pages.filter((page) => page.kind === 'photo');
    const files: { file: File; label: string }[] = [];

    if (photos.length === 1) {
        const image = await backend.load(photos[0]);
        files.push({
            file: fileOf([image.jpeg as BlobPart], 'scan.jpg', 'image/jpeg'),
            label: trans('The photo'),
        });
    } else if (photos.length > 1) {
        const images: PageImage[] = [];

        // one at a time: a photo read at full size takes tens of MB, and a phone has little to spare
        for (const photo of photos) {
            images.push(await backend.load(photo));
        }
        const single = oneSheet && oneSheetState(pages) === 'ready';
        const sheet = single
            ? await backend.sheet(images, sheetPlan(images))
            : null;

        files.push({
            file: fileOf(
                [makePdf((sheet ? [sheet] : images).map(pdfPage))],
                'scan.pdf',
                'application/pdf',
            ),
            label: single
                ? trans('The PDF of the page')
                : trans('The PDF of the pages'),
        });
    }

    for (const page of pages) {
        if (page.kind === 'pdf') {
            files.push({ file: page.file, label: page.file.name });
        }
    }

    const heavy = files.find(({ file }) => file.size > MAX_UPLOAD_BYTES);

    if (heavy) {
        throw new ScanTooHeavyError(heavy.label, heavy.file.size);
    }

    return files.map(({ file }) => file);
}
