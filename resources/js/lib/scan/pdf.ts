// Composes a PDF from the sides of a scan, one page per side: each JPEG goes in as it is (a PDF can hold a JPEG
// without decoding it), so nothing is compressed twice. No library: the file is written by hand. One copy, from
// `phone-scan/public/pdf.js` and `document-reader/src/module/scan/pdf.ts` of the old extensions.

/** The long side of a page, in points: the one of an A4 sheet. The page takes the shape of its side. */
const PAGE_LONG_SIDE = 842;

/** How much a page is turned, in degrees clockwise. */
export type Rotation = 0 | 90 | 180 | 270;

/**
 * One page: a JPEG with its size in pixels, whether it is a grey one (`/DeviceGray`) or in colour (`/DeviceRGB`),
 * and how much the viewer turns it, in degrees clockwise (`/Rotate`; none when missing or 0).
 */
export type PdfPage = {
    jpeg: Uint8Array;
    width: number;
    height: number;
    grey: boolean;
    rotate?: Rotation;
};

/**
 * How many colours a JPEG holds, read from its header: 1 for a side scanned in grey, 3 for one in colour. What the
 * file says counts, not what was asked of the scanner: a driver may answer in colour to a request in grey.
 *
 * @param jpeg - the bytes of the JPEG
 * @returns the number of colours of its frame, 3 when it has none
 */
export function jpegColours(jpeg: Uint8Array): number {
    let at = 2;

    while (at + 9 < jpeg.length) {
        const marker = jpeg[at + 1]!;

        if (jpeg[at] !== 0xff || marker === 0xff) {
            at++;
            continue;
        }

        // the markers without a length
        if (marker === 0x01 || (marker >= 0xd0 && marker <= 0xd9)) {
            at += 2;
            continue;
        }

        // the start of the frame, which says the size and the colours: not the tables that share its range
        if (
            marker >= 0xc0 &&
            marker <= 0xcf &&
            marker !== 0xc4 &&
            marker !== 0xc8 &&
            marker !== 0xcc
        ) {
            return jpeg[at + 9]!;
        }

        at += 2 + ((jpeg[at + 2]! << 8) | jpeg[at + 3]!);
    }

    return 3;
}

/** Rounds to two decimals. */
const round = (value: number): number => Math.round(value * 100) / 100;

/**
 * Writes the PDF of some pages.
 *
 * @param pages the pages, in order
 * @returns the PDF file (`application/pdf`)
 */
export function makePdf(pages: PdfPage[]): Blob {
    const encoder = new TextEncoder();
    const parts: Uint8Array[] = [];
    const offsets: number[] = [];
    let length = 0;

    const push = (chunk: string | Uint8Array): void => {
        const bytes = typeof chunk === 'string' ? encoder.encode(chunk) : chunk;
        parts.push(bytes);
        length += bytes.length;
    };
    const object = (number: number, ...body: (string | Uint8Array)[]): void => {
        offsets[number] = length;
        push(`${number} 0 obj\n`);
        for (const chunk of body) push(chunk);
        push('\nendobj\n');
    };

    // three objects per side, after the catalog (1) and the list of the pages (2): page, drawing, image
    const pageNumber = (index: number): number => 3 + index * 3;

    push('%PDF-1.4\n');
    // a comment of bytes above 127: tells the programs that the file is not text
    push(new Uint8Array([0x25, 0xe2, 0xe3, 0xcf, 0xd3, 0x0a]));
    object(1, '<< /Type /Catalog /Pages 2 0 R >>');
    object(
        2,
        `<< /Type /Pages /Count ${pages.length} /Kids [${pages.map((_, index) => `${pageNumber(index)} 0 R`).join(' ')}] >>`,
    );

    for (const [index, page] of pages.entries()) {
        const scale = PAGE_LONG_SIDE / Math.max(page.width, page.height);
        const width = round(page.width * scale);
        const height = round(page.height * scale);
        const number = pageNumber(index);
        const drawing = `q ${width} 0 0 ${height} 0 0 cm /Im0 Do Q`;
        // the page is drawn as the JPEG is, and the viewer turns it: the JPEG is not compressed again
        const rotate = page.rotate ? `/Rotate ${page.rotate} ` : '';
        const colours = page.grey ? '/DeviceGray' : '/DeviceRGB';

        object(
            number,
            `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${width} ${height}] ${rotate}` +
                `/Resources << /XObject << /Im0 ${number + 2} 0 R >> >> /Contents ${number + 1} 0 R >>`,
        );
        object(
            number + 1,
            `<< /Length ${drawing.length} >>\nstream\n${drawing}\nendstream`,
        );
        object(
            number + 2,
            `<< /Type /XObject /Subtype /Image /Width ${page.width} /Height ${page.height} ` +
                `/ColorSpace ${colours} /BitsPerComponent 8 /Filter /DCTDecode /Length ${page.jpeg.length} >>\nstream\n`,
            page.jpeg,
            '\nendstream',
        );
    }

    const last = 2 + pages.length * 3;
    const table = length;
    push(`xref\n0 ${last + 1}\n0000000000 65535 f \n`);
    for (let number = 1; number <= last; number++)
        push(`${String(offsets[number]).padStart(10, '0')} 00000 n \n`);
    push(
        `trailer\n<< /Size ${last + 1} /Root 1 0 R >>\nstartxref\n${table}\n%%EOF\n`,
    );

    return new Blob(parts as BlobPart[], { type: 'application/pdf' });
}
