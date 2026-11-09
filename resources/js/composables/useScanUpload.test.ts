import { render } from '@testing-library/vue';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h, ref } from 'vue';
import type { Backend, ScanPage } from '@/lib/scan/pages';
import type { UseScanUpload } from './useScanUpload';
import { useScanUpload } from './useScanUpload';

const server = vi.hoisted(() => ({
    sendScanFile: vi.fn(),
    enterAgain: vi.fn(),
}));

vi.mock('@/lib/scan/server', async (original) => ({
    ...(await original<typeof import('@/lib/scan/server')>()),
    sendScanFile: server.sendScanFile,
    enterAgain: server.enterAgain,
}));

const { ScanRequestError } = await import('@/lib/scan/server');

/** A photo cut out, or a PDF chosen on the phone. */
function page(
    id: string,
    kind: 'photo' | 'pdf' = 'photo',
    size = 10,
): ScanPage {
    return {
        id,
        file: new File(
            [new Uint8Array(size)],
            `${id}.${kind === 'pdf' ? 'pdf' : 'jpg'}`,
        ),
        kind,
        corners: null,
        turns: 0,
        improve: { enhance: true, grey: true, dewarp: false },
        cut: true,
        jpeg: new Blob(['j'], { type: 'image/jpeg' }),
    };
}

/** A backend that draws nothing. */
const backend: Backend = {
    load: async (one) => ({
        jpeg: new TextEncoder().encode(`jpeg-${one.id}`),
        width: 10,
        height: 10,
        grey: false,
    }),
    sheet: async () => ({
        jpeg: new TextEncoder().encode('sheet'),
        width: 10,
        height: 10,
        grey: false,
    }),
};

/** Mounts the upload over a list of pages that the arrived files leave. */
function mountUpload(start: ScanPage[], note = '', oneSheet = false) {
    const pages = ref<ScanPage[]>(start);
    const finished = vi.fn();
    let upload: UseScanUpload | undefined;

    render(
        defineComponent({
            setup() {
                upload = useScanUpload({
                    pages: () => pages.value,
                    oneSheet: () => oneSheet,
                    note: () => note,
                    onArrived: (ids) => {
                        pages.value = pages.value.filter(
                            (one) => !ids.includes(one.id),
                        );
                    },
                    onFinished: finished,
                    backend,
                });

                return () => h('div');
            },
        }),
    );

    return { upload: upload!, pages, finished };
}

/** An answer of the server for a file saved. */
const saved = (file: File) => [{ id: 1, name: file.name, size: file.size }];

beforeEach(() => {
    server.sendScanFile
        .mockReset()
        .mockImplementation(async (file: File) => saved(file));
    server.enterAgain.mockReset().mockResolvedValue(true);
});

describe('useScanUpload', () => {
    it('asks first, with what leaves', () => {
        const { upload } = mountUpload(
            [page('a'), page('b'), page('c')],
            'polizza casa 2026',
        );

        upload.ask();

        expect(upload.asking.value).toBe(true);
        expect(upload.question.value).toBe('Upload 3 pages?');
        expect(upload.details.value).toBe(
            '«polizza casa 2026» · 3 pages, in a single PDF',
        );
    });

    it('asks about the photos on a single page', () => {
        const { upload } = mountUpload([page('a'), page('b')], '', true);

        expect(upload.question.value).toBe('Upload 2 photos on a single page?');
    });

    it('sends the photos as one PDF and says what arrived', async () => {
        const { upload, pages, finished } = mountUpload(
            [page('a', 'photo'), page('b', 'photo'), page('c', 'photo')],
            'polizza casa 2026',
        );

        await upload.send();

        expect(server.sendScanFile).toHaveBeenCalledTimes(1);
        const [file, note] = server.sendScanFile.mock.calls[0];
        expect((file as File).type).toBe('application/pdf');
        expect(note).toBe('polizza casa 2026');
        expect(pages.value).toEqual([]);
        expect(finished).toHaveBeenCalled();
        expect(upload.done.value).toMatch(/^Uploaded: 3 pages, \d+ K?B$/);
        expect(upload.sending.value).toBe(false);
    });

    it('tells the progress of the files', async () => {
        const seen: number[] = [];
        const { upload } = mountUpload([page('a'), page('p', 'pdf')]);
        server.sendScanFile.mockImplementation(
            async (
                file: File,
                _note: string,
                progress: (f: number) => void,
            ) => {
                progress(0.5);
                seen.push(upload.progress.value);

                return saved(file);
            },
        );

        await upload.send();

        // two files: half of the first is a quarter, half of the second three quarters
        expect(seen).toEqual([25, 75]);
    });

    it('keeps what did not arrive, and Retry sends only the rest', async () => {
        const { upload, pages } = mountUpload([page('a'), page('p', 'pdf')]);
        server.sendScanFile
            .mockImplementationOnce(async (file: File) => saved(file))
            .mockRejectedValueOnce(new ScanRequestError(0, 'No network.'));

        await upload.send();

        expect(pages.value.map((one) => one.id)).toEqual(['p']);
        expect(upload.error.value).toBe(
            'No network. What was not uploaded is still here.',
        );
        expect(upload.canRetry.value).toBe(true);

        await upload.send();

        expect(server.sendScanFile).toHaveBeenCalledTimes(3);
        expect((server.sendScanFile.mock.calls[2][0] as File).name).toBe(
            'p.pdf',
        );
        expect(pages.value).toEqual([]);
        expect(upload.error.value).toBeNull();
        expect(upload.done.value).toMatch(/^Uploaded: 2 files, /);
    });

    it('enters again after a 419 and retries, without losing the pages', async () => {
        const { upload, pages } = mountUpload([page('a'), page('b')]);
        server.sendScanFile.mockRejectedValueOnce(
            new ScanRequestError(419, 'Page Expired'),
        );

        await upload.send();

        expect(server.enterAgain).toHaveBeenCalledTimes(1);
        expect(server.sendScanFile).toHaveBeenCalledTimes(2);
        expect(pages.value).toEqual([]);
        expect(upload.error.value).toBeNull();
    });

    it('says to sign in again when the phone cannot enter, with the pages still there', async () => {
        server.enterAgain.mockResolvedValue(false);
        server.sendScanFile.mockRejectedValue(
            new ScanRequestError(401, 'Unauthenticated.'),
        );
        const { upload, pages } = mountUpload([page('a'), page('b')]);

        await upload.send();

        expect(pages.value).toHaveLength(2);
        expect(upload.error.value).toBe(
            'Your session has ended: sign in again. The pages are still here.',
        );
    });

    it('says a file is too heavy before sending anything', async () => {
        const { upload } = mountUpload([page('p', 'pdf', 26214401)]);

        await upload.send();

        expect(server.sendScanFile).not.toHaveBeenCalled();
        expect(upload.error.value).toBe(
            'p.pdf weighs 25 MB: the most is 25 MB. Take away some pages and upload in two goes.',
        );
        expect(upload.canRetry.value).toBe(false);
    });
});
