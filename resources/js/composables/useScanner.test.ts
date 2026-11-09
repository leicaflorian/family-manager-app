import { http } from '@inertiajs/vue3';
import { render, waitFor } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { LAUNCHING_FOR, LAUNCH_WAIT, useScanner } from './useScanner';
import type { UseScanner } from './useScanner';
import {
    fakeComposedSheet,
    fakeImprovedJpeg,
    fakeMeasureSide,
    fakeScanAgent,
    resetScanAgent,
    scanAgent,
    tagOf,
    tagged,
} from '@/test/scanAgent';

// The work on the pixels needs a real canvas: here a side made again is the original with what was asked written in
const canvas = vi.hoisted(() => ({
    measureSide: vi.fn(),
    improvedJpeg: vi.fn(),
    composedSheet: vi.fn(),
}));

vi.mock('@/lib/scan/canvas', () => canvas);

vi.mock('@/lib/scan/blank', () => ({
    isBlank: (image: { blank: boolean }) => image.blank,
}));

const launch = vi.hoisted(() => vi.fn());

vi.mock('@/lib/scan/agent', async (original) => ({
    ...(await original<typeof import('@/lib/scan/agent')>()),
    launch,
}));

const MAC =
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Chrome/130 Safari/537.36';

type Client = ReturnType<typeof http.getClient>;
type Config = Parameters<Client['request']>[0];

const real = http.getClient();
const upload = vi.fn<(config: Config) => Promise<unknown>>();

/** Mounts a component that drives the scanner and gives it back. */
function mountScanner(): { scanner: UseScanner; unmount: () => void } {
    let scanner: UseScanner | undefined;
    const view = render(
        defineComponent({
            setup() {
                scanner = useScanner();

                return () => h('div');
            },
        }),
    );

    return { scanner: scanner!, unmount: () => view.unmount() };
}

/** Waits for the agent found and the scanner ready to scan. */
async function connected(scanner: UseScanner): Promise<void> {
    await waitFor(() => expect(scanner.canScan.value).toBe(true));
}

beforeEach(() => {
    resetScanAgent();
    localStorage.clear();
    launch.mockReset();
    canvas.measureSide.mockReset().mockImplementation(fakeMeasureSide);
    canvas.improvedJpeg.mockReset().mockImplementation(fakeImprovedJpeg);
    canvas.composedSheet.mockReset().mockImplementation(fakeComposedSheet);
    vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(MAC);
    vi.stubGlobal('fetch', vi.fn(fakeScanAgent));
    vi.stubGlobal(
        'createImageBitmap',
        vi.fn(async (blob: Blob) => ({
            width: 1000,
            height: 1400,
            blank: tagOf(blob) === 'blank',
            close: () => {},
        })),
    );
    vi.stubGlobal(
        'URL',
        Object.assign(URL, {
            createObjectURL: vi.fn((blob: Blob) => `blob:${tagOf(blob)}`),
            revokeObjectURL: vi.fn(),
        }),
    );
    upload.mockReset().mockResolvedValue({
        status: 200,
        data: JSON.stringify({ files: [{ id: 1, name: 'x', size: 1 }] }),
        headers: {},
    });
    http.setClient({
        request: (config: Config) => upload(config),
    } as unknown as Client);
});

afterEach(() => {
    http.setClient(real);
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('useScanner, the agent', () => {
    it('takes up the sides the agent already holds when the page is reloaded', async () => {
        scanAgent.pages = [
            { id: '1', blob: tagged('before-1') },
            { id: '2', blob: tagged('before-2') },
        ];
        scanAgent.state = 'done';

        const { scanner } = mountScanner();

        await waitFor(() =>
            expect(scanner.kept.value.map((side) => tagOf(side.blob))).toEqual([
                'before-1',
                'before-2',
            ]),
        );
        expect(scanAgent.calls.some((call) => call.startsWith('POST'))).toBe(
            false,
        );
    });

    it('follows a scan already started from another tab as its own', async () => {
        const { scanner } = mountScanner();
        await connected(scanner);
        scanAgent.busy = true;
        scanAgent.state = 'scanning';
        scanAgent.scanningFor = 1;

        const started = scanner.startScan();

        await waitFor(() => expect(scanner.scanning.value).toBe(true));
        await started;
        expect(scanner.failure.value).toBeNull();
        expect(scanner.kept.value.map((side) => tagOf(side.blob))).toEqual([
            'sheet-1',
            'sheet-2',
        ]);
    });

    it('does not take the answer of the scanner chosen before', async () => {
        scanAgent.scanners = [
            { id: 'sp', name: 'SP-1120N', connection: 'usb' },
            { id: 'canon', name: 'Canon', connection: 'network' },
        ];
        const { scanner } = mountScanner();
        await waitFor(() => expect(scanner.phase.value).toBe('connected'));
        scanner.chooseScanner('sp');
        await connected(scanner);
        let release = (): void => undefined;
        scanAgent.hold = (call) =>
            call.startsWith('GET /status') && call.includes('scanner=sp')
                ? new Promise<void>((resolve) => (release = resolve))
                : null;

        // the page asks how the first scanner is, and the answer is late
        document.dispatchEvent(new Event('visibilitychange'));
        await waitFor(() =>
            expect(
                scanAgent.calls.filter((call) => call.includes('scanner=sp')),
            ).not.toHaveLength(0),
        );
        scanAgent.scanner = 'empty';
        scanner.chooseScanner('canon');
        await waitFor(() => expect(scanner.scannerState.value).toBe('empty'));

        scanAgent.scanner = 'ready';
        release();
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(scanner.scannerId.value).toBe('canon');
        expect(scanner.scannerState.value).toBe('empty');
    });

    it('asks the system to start the agent, waits five seconds for it, then says it is missing', async () => {
        vi.useFakeTimers();
        scanAgent.mode = 'down';

        const { scanner } = mountScanner();
        await vi.advanceTimersByTimeAsync(0);

        expect(launch).toHaveBeenCalledTimes(1);
        expect(scanner.launching.value).toBe(true);

        await vi.advanceTimersByTimeAsync(LAUNCHING_FOR);
        expect(scanner.launching.value).toBe(false);
        expect(scanner.phase.value).toBe('searching');

        await vi.advanceTimersByTimeAsync(LAUNCH_WAIT);
        expect(scanner.phase.value).toBe('missing');
        expect(launch).toHaveBeenCalledTimes(1);
    });

    it('does not start a scan while one is running or with the agent closed', async () => {
        const { scanner } = mountScanner();
        await connected(scanner);
        scanAgent.mode = 'down';
        scanner.phase.value = 'stopped';

        await scanner.startScan();

        expect(scanAgent.calls.some((call) => call.startsWith('POST'))).toBe(
            false,
        );
    });
});

describe('useScanner, the sides', () => {
    /** A scan of the sheets set in the agent. */
    async function scanned(sheets: string[]): Promise<UseScanner> {
        scanAgent.sheets = sheets;
        const { scanner } = mountScanner();
        await connected(scanner);
        await scanner.startScan();

        return scanner;
    }

    it('turns a side by a quarter only in the PDF, without making its JPEG again', async () => {
        const scanner = await scanned(['sheet-1']);
        const side = scanner.kept.value[0]!;

        scanner.rotate(side.id);

        expect(side.rotation).toBe(90);
        expect(canvas.improvedJpeg).not.toHaveBeenCalled();
    });

    it('straightens the crooked sides by as much as they went in crooked', async () => {
        const scanner = await scanned(['crooked-1', 'sheet-2']);

        expect(scanner.crooked.value).toHaveLength(1);
        scanner.straightenAll();

        await waitFor(() =>
            expect(tagOf(scanner.kept.value[0]!.blob)).toBe(
                'crooked-1@-1.5/0/false',
            ),
        );
        expect(scanner.crooked.value).toHaveLength(0);
    });

    it('makes a side again only once the slider has stopped', async () => {
        const scanner = await scanned(['sheet-1']);
        const side = scanner.kept.value[0]!;

        scanner.setEnhance(side.id, 30);
        scanner.setEnhance(side.id, 55);

        await waitFor(() =>
            expect(tagOf(side.blob)).toBe('sheet-1@0/55/false'),
        );
        expect(canvas.improvedJpeg).toHaveBeenCalledTimes(1);
    });

    it('with "On a single page" puts the cards that arrive on one composed page, and separates them', async () => {
        scanAgent.sheets = ['card-front', 'card-back'];
        const { scanner } = mountScanner();
        await connected(scanner);
        scanner.setOneSheet(true);
        await scanner.startScan();

        await waitFor(() =>
            expect(tagOf(scanner.kept.value[0]!.blob)).toBe(
                'sheet(card-front,card-back)',
            ),
        );
        expect(scanner.kept.value).toHaveLength(1);
        expect(scanner.cardSides.value).toBe(2);

        scanner.separate(scanner.kept.value[0]!.id);

        expect(scanner.kept.value.map((side) => tagOf(side.blob))).toEqual([
            'card-front',
            'card-back',
        ]);
        expect(scanner.loose.value).toHaveLength(2);
    });

    it('puts the loose cards on one page with "Put on one page"', async () => {
        const scanner = await scanned(['card-1', 'sheet', 'card-2']);

        expect(scanner.loose.value).toHaveLength(2);
        scanner.gatherLoose();

        await waitFor(() =>
            expect(scanner.kept.value.map((side) => tagOf(side.blob))).toEqual([
                'sheet(card-1,card-2)',
                'sheet',
            ]),
        );
    });

    it('weighs the sides and stops Upload over 25 MB', async () => {
        const scanner = await scanned(['sheet-1', 'sheet-2']);
        scanner.kept.value[0]!.blob = new Blob([new Uint8Array(26214400)]);

        expect(scanner.tooLarge.value).toBe(true);
        expect(await scanner.upload('')).toBe(false);
        expect(upload).not.toHaveBeenCalled();

        scanner.onePerSide.value = true;
        expect(scanner.tooLarge.value).toBe(false);
    });

    it('sends a PDF per side, numbered, and after one that stopped goes on from the next number', async () => {
        const scanner = await scanned(['sheet-1', 'sheet-2', 'sheet-3']);
        scanner.onePerSide.value = true;
        upload
            .mockResolvedValueOnce({
                status: 200,
                data: JSON.stringify({ files: [] }),
                headers: {},
            })
            .mockRejectedValueOnce(
                Object.assign(new Error('failed'), {
                    response: { status: 500, data: '{}', headers: {} },
                }),
            );

        expect(scanner.kept.value).toHaveLength(3);
        expect(await scanner.upload('polizza')).toBe(false);
        expect(scanner.uploadError.value).toMatch(
            /^1 side of 3 uploaded, the others not:/,
        );
        expect(scanner.kept.value).toHaveLength(2);

        expect(await scanner.upload('polizza')).toBe(true);
        const names = upload.mock.calls.map((call) =>
            (call[0].data as FormData).get('name'),
        );
        expect(names).toEqual([
            'polizza 1.pdf',
            'polizza 2.pdf',
            'polizza 2.pdf',
            'polizza 3.pdf',
        ]);
    });
});
