import { describe, expect, it } from 'vite-plus/test';
import { MAX_UPLOAD_BYTES, ScanTooHeavyError } from './pages';
import { scannerFile } from './scannerFile';

/** The start of a JPEG with a frame of `colours` components and a tag after it, to find the side in the PDF. */
function jpeg(colours: number, tag: string, size = 0): Blob {
    const header = new Uint8Array([
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
    ]);

    return new Blob([
        header,
        tag,
        new Uint8Array(size),
        new Uint8Array([0xff, 0xd9]),
    ]);
}

/** The text of a PDF. */
const textOf = async (file: Blob): Promise<string> =>
    new TextDecoder('latin1').decode(await file.arrayBuffer());

describe('scannerFile', () => {
    it('makes one PDF with a page for each side, in order, each JPEG as it is', async () => {
        const file = await scannerFile([
            { blob: jpeg(3, 'first'), width: 100, height: 140, rotation: 0 },
            { blob: jpeg(3, 'second'), width: 100, height: 140, rotation: 0 },
        ]);
        const text = await textOf(file);

        expect(file.type).toBe('application/pdf');
        expect(text.match(/\/Type \/Page\b/g)).toHaveLength(2);
        expect(text.indexOf('first')).toBeLessThan(text.indexOf('second'));
    });

    it('writes a side scanned in grey as grey, read from its JPEG', async () => {
        const text = await textOf(
            await scannerFile([
                { blob: jpeg(1, 'grey'), width: 100, height: 140, rotation: 0 },
                {
                    blob: jpeg(3, 'colour'),
                    width: 100,
                    height: 140,
                    rotation: 0,
                },
            ]),
        );

        expect(text.match(/\/DeviceGray/g)).toHaveLength(1);
        expect(text.match(/\/DeviceRGB/g)).toHaveLength(1);
    });

    it('turns a side with /Rotate, without making the JPEG again', async () => {
        const text = await textOf(
            await scannerFile([
                {
                    blob: jpeg(3, 'turned'),
                    width: 100,
                    height: 140,
                    rotation: 90,
                },
            ]),
        );

        expect(text).toContain('/Rotate 90 ');
        expect(text).toContain('/Width 100 /Height 140');
    });

    it('stops a PDF over 25 MB', async () => {
        await expect(
            scannerFile([
                {
                    blob: jpeg(1, 'big', MAX_UPLOAD_BYTES),
                    width: 100,
                    height: 140,
                    rotation: 0,
                },
            ]),
        ).rejects.toThrow(ScanTooHeavyError);
    });
});
