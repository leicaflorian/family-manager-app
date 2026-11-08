import { describe, expect, it } from 'vite-plus/test';
import { makePdf } from './pdf';

/** A minimal baseline JPEG header with a frame of `colours` components (enough for the header reader). */
function jpegWith(colours: number): Uint8Array {
    return new Uint8Array([
        0xff,
        0xd8,
        0xff,
        0xc0,
        0x00,
        0x0b,
        0x08,
        0x00,
        0x10,
        0x00,
        0x20,
        colours,
        0x01,
        0x11,
        0x00,
        0xff,
        0xd9,
    ]);
}

async function text(blob: Blob): Promise<string> {
    return new TextDecoder('latin1').decode(await blob.arrayBuffer());
}

describe('makePdf', () => {
    const pdf = makePdf([
        { jpeg: jpegWith(1), width: 1000, height: 2000, grey: true },
        {
            jpeg: jpegWith(3),
            width: 2000,
            height: 1000,
            grey: false,
            rotate: 90,
        },
    ]);

    it('is a PDF file', async () => {
        const body = await text(pdf);
        expect(pdf.type).toBe('application/pdf');
        expect(body.startsWith('%PDF-')).toBe(true);
        expect(body.trimEnd().endsWith('%%EOF')).toBe(true);
    });

    it('writes one gray and one color page, the second rotated', async () => {
        const body = await text(pdf);
        expect(body).toContain('/Count 2');
        expect(body).toContain('/ColorSpace /DeviceGray');
        expect(body).toContain('/ColorSpace /DeviceRGB');
        expect(body.match(/\/Filter \/DCTDecode/g)).toHaveLength(2);
        expect(body.match(/\/Rotate 90 /g)).toHaveLength(1);
        expect(body).not.toContain('/Rotate 0');
    });

    it('gives each page a long side of 842 points', async () => {
        const body = await text(pdf);
        expect(body).toContain('/MediaBox [0 0 421 842]');
        expect(body).toContain('/MediaBox [0 0 842 421]');
    });

    it('points the xref at the objects', async () => {
        const body = await text(pdf);
        const start = Number(/startxref\n(\d+)/.exec(body)?.[1]);
        expect(body.slice(start, start + 4)).toBe('xref');
        const first = /1 0 obj/.exec(body)?.index;
        const entry = /xref\n0 \d+\n0000000000 65535 f \n(\d{10})/.exec(
            body,
        )?.[1];
        expect(Number(entry)).toBe(first);
    });
});
