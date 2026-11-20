import { http, router } from '@inertiajs/vue3';
import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { WIDE_QUERY } from '@/composables/useWideLayout';
import { renderPage, settle } from '@/test/page';
import Scan from './Scan.vue';

const page = vi.hoisted(() => ({ linked: false, url: '/scan' }));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({
        url: page.url,
        props: {
            name: 'Fameja.app',
            auth: { user: { name: 'Laura Bianchi' } },
            family: { name: 'Bianchi' },
            linked_phone: page.linked,
        },
    }),
}));

// The files of Share are in the cache of the service worker, which a test has not: here the page is handed some
const shared = vi.hoisted(() => ({ take: vi.fn() }));

vi.mock('@/lib/pwa/sharedFiles', async (original) => ({
    ...(await original<typeof import('@/lib/pwa/sharedFiles')>()),
    takeShared: shared.take,
}));

// Whether a page of the app comes before the scan in this tab: here the test says it
const trail = vi.hoisted(() => ({ canGoBack: vi.fn(() => false) }));

vi.mock('@/lib/visitHistory', async (original) => ({
    ...(await original<typeof import('@/lib/visitHistory')>()),
    canGoBack: trail.canGoBack,
}));

// The work on the pixels needs a real canvas: here each step gives a named file, so the test sees which one is shown
const canvas = vi.hoisted(() => ({
    reduceToJpeg: vi.fn(),
    cutOut: vi.fn(),
    improvedJpeg: vi.fn(),
}));

vi.mock('@/lib/scan/canvas', () => ({
    SEARCH_LONG_SIDE: 1000,
    reduceToJpeg: canvas.reduceToJpeg,
    cutOut: canvas.cutOut,
    improvedJpeg: canvas.improvedJpeg,
    canvasBackend: {
        load: async (one: { id: string }) => ({
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
    },
}));

// *Straighten* needs a worker and a model: here the model is a fake that the test answers
const straighten = vi.hoisted(() => ({
    available: vi.fn<() => boolean>(),
    dewarp: vi.fn<(sheet: unknown, signal?: AbortSignal) => Promise<unknown>>(),
}));

vi.mock('@/lib/scan/dewarp', async (original) => ({
    ...(await original<typeof import('@/lib/scan/dewarp')>()),
    isDewarpAvailable: straighten.available,
    dewarp: straighten.dewarp,
}));

type Client = ReturnType<typeof http.getClient>;
type Config = Parameters<Client['request']>[0];

const real = http.getClient();
/** The server: the corners and the upload, answered by the test. */
const server = {
    corners: vi.fn<(config: Config) => Promise<unknown>>(),
    upload: vi.fn<(config: Config) => Promise<unknown>>(),
};

/** An answer of the HTTP client. */
const answer = (body: unknown) => ({
    status: 200,
    data: JSON.stringify(body),
    headers: {},
});

/** An error of the HTTP client, with the answer of the server. */
const refusal = (status: number, body: unknown = {}) =>
    Object.assign(new Error(`Request failed with status ${status}`), {
        response: { status, data: JSON.stringify(body), headers: {} },
    });

const CORNERS = [
    { x: 0.1, y: 0.1 },
    { x: 0.9, y: 0.12 },
    { x: 0.88, y: 0.9 },
    { x: 0.12, y: 0.88 },
];

/** A photo as the phone gives it. */
const photo = (name: string): File =>
    new File(['photo'], name, { type: 'image/jpeg' });

/** Chooses files with *From the phone*. */
async function choose(...files: File[]): Promise<void> {
    await fireEvent.change(screen.getByTestId('files-input'), {
        target: { files },
    });
}

/** The address of the picture in the large view. */
const shown = (): string | null =>
    document.querySelector('[data-preview] img')?.getAttribute('src') ?? null;

/** Makes the window wide, as a computer. */
function wide(on: boolean): void {
    vi.stubGlobal('matchMedia', (query: string) => ({
        matches: on && query === WIDE_QUERY,
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
    }));
}

beforeEach(() => {
    page.linked = false;
    page.url = '/scan';
    trail.canGoBack.mockReset().mockReturnValue(false);
    shared.take.mockReset().mockResolvedValue([]);
    wide(false);
    let urls = 0;
    vi.stubGlobal(
        'URL',
        Object.assign(URL, {
            createObjectURL: vi.fn((blob: Blob) =>
                blob instanceof File ? `blob:${blob.name}` : `blob:${++urls}`,
            ),
            revokeObjectURL: vi.fn(),
        }),
    );
    canvas.reduceToJpeg
        .mockReset()
        .mockImplementation(
            async (_file: Blob, side: number) =>
                new File(['j'], `photo-${side}.jpg`, { type: 'image/jpeg' }),
        );
    canvas.cutOut.mockReset().mockImplementation(async () => ({
        jpeg: new File(['s'], 'sheet.jpg', { type: 'image/jpeg' }),
        plain: new File(['p'], 'plain.jpg', { type: 'image/jpeg' }),
    }));
    canvas.improvedJpeg.mockReset().mockImplementation(
        async (_plain: Blob, wanted: { enhance: number; grey: boolean }) =>
            new File(['i'], `improved-${wanted.enhance}.jpg`, {
                type: 'image/jpeg',
            }),
    );
    straighten.available.mockReset().mockReturnValue(false);
    straighten.dewarp.mockReset().mockResolvedValue({
        width: 1,
        height: 1,
        data: new Uint8ClampedArray(4),
    });
    server.corners.mockReset().mockResolvedValue(answer({ corners: CORNERS }));
    server.upload
        .mockReset()
        .mockResolvedValue(answer({ files: [{ id: 1, name: 'x', size: 1 }] }));
    http.setClient({
        request: (config: Config) =>
            config.url.endsWith('/scan/corners')
                ? server.corners(config)
                : server.upload(config),
    } as unknown as Client);
});

afterEach(() => {
    http.setClient(real);
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

/** Pretends to be a phone's browser at an address. */
function phoneBrowser(agent: string, protocol = 'https:'): void {
    vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(agent);
    vi.stubGlobal('location', { protocol });
}

const IPHONE =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';
const ANDROID =
    'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 Chrome/130 Mobile Safari/537.36';

describe('Scan, the files of Share', () => {
    it('adds the shared files as pages when opened with ?shared=1, and asks the cache once', async () => {
        page.url = '/scan?shared=1';
        shared.take.mockResolvedValue([
            photo('whatsapp.jpg'),
            new File(['pdf'], 'pagella.pdf', { type: 'application/pdf' }),
        ]);

        renderPage(Scan);

        expect(await screen.findByText('2 files')).toBeInTheDocument();
        expect(shared.take).toHaveBeenCalledOnce();
    });

    it('takes nothing from the cache without ?shared=1', async () => {
        renderPage(Scan);
        await settle();

        expect(shared.take).not.toHaveBeenCalled();
    });

    it('opens with no pages when the cache has nothing', async () => {
        page.url = '/scan?shared=1';

        renderPage(Scan);
        await settle();

        expect(screen.getByText('No pages')).toBeInTheDocument();
    });
});

describe('Scan, opened from Add with ?camera=1', () => {
    it('opens the camera of the phone at once, and takes ?camera=1 off the address', async () => {
        page.url = '/scan?camera=1';
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(ANDROID);
        const click = vi
            .spyOn(HTMLInputElement.prototype, 'click')
            .mockImplementation(() => {});
        const replace = vi
            .spyOn(router, 'replace')
            .mockImplementation(() => {});

        renderPage(Scan);
        await settle();

        expect(click).toHaveBeenCalledOnce();
        expect(click.mock.contexts[0]).toHaveAttribute(
            'capture',
            'environment',
        );
        expect(replace).toHaveBeenCalledWith(
            expect.objectContaining({ url: '/scan', preserveState: true }),
        );
    });

    it('waits for Take a picture without ?camera=1', async () => {
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(ANDROID);
        const click = vi
            .spyOn(HTMLInputElement.prototype, 'click')
            .mockImplementation(() => {});

        renderPage(Scan);
        await settle();

        expect(click).not.toHaveBeenCalled();
    });
});

describe('Scan, the sheet Install Fameja.app', () => {
    afterEach(() => localStorage.clear());

    it('shows the steps on an iPhone in the browser, and Not now closes it', async () => {
        phoneBrowser(IPHONE);
        renderPage(Scan);

        const sheet = await screen.findByRole('dialog', {
            name: 'Install Fameja.app',
        });
        expect(
            within(sheet).getByText(/Add to Home Screen/),
        ).toBeInTheDocument();
        expect(
            within(sheet).queryByRole('button', { name: 'Install' }),
        ).not.toBeInTheDocument();

        await fireEvent.click(
            within(sheet).getByRole('button', { name: 'Not now' }),
        );
        await waitFor(() =>
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
        );
    });

    it('has the Install button on Android, which shows the steps when the phone made no invitation', async () => {
        phoneBrowser(ANDROID);
        renderPage(Scan);

        const sheet = await screen.findByRole('dialog', {
            name: 'Install Fameja.app',
        });
        expect(
            within(sheet).queryByText(/Install app/),
        ).not.toBeInTheDocument();

        await fireEvent.click(
            within(sheet).getByRole('button', { name: 'Install' }),
        );

        expect(within(sheet).getByText(/Install app/)).toBeInTheDocument();
    });

    it('does not come up over http, nor on a computer', async () => {
        phoneBrowser(ANDROID, 'http:');
        renderPage(Scan);
        await settle();

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
});

describe('Scan', () => {
    it('starts empty, with the phone and the camera', () => {
        renderPage(Scan);

        expect(
            screen.getByRole('heading', { name: 'New document' }),
        ).toBeInTheDocument();
        expect(screen.getByText('No pages')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'From the phone' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Take a picture' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Upload' })).toBeDisabled();
    });

    it('shows the photo cut out, with Cropped, when the corners come back', async () => {
        renderPage(Scan);

        await choose(photo('receipt.jpg'));

        expect(await screen.findByText('Cropped')).toBeInTheDocument();
        expect(shown()).toBe('blob:sheet.jpg');
        // the 1000 px copy went to the server, the sheet was cut with its corners and the defaults
        expect(canvas.reduceToJpeg).toHaveBeenCalledWith(
            expect.anything(),
            1000,
            expect.any(Number),
        );
        expect(canvas.cutOut).toHaveBeenCalledWith(
            expect.any(File),
            CORNERS,
            0,
            { enhance: 50, grey: true },
        );
        expect(screen.getByText('1 page')).toBeInTheDocument();
    });

    it('leaves the photo as it is when the server does not answer in 20 seconds', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        server.corners.mockImplementation(() => new Promise(() => {}));
        renderPage(Scan);

        await choose(photo('receipt.jpg'));
        await vi.advanceTimersByTimeAsync(20000);

        await waitFor(() => expect(shown()).toBe('blob:photo-2000.jpg'));
        expect(screen.queryByText('Cropped')).not.toBeInTheDocument();
        expect(canvas.cutOut).not.toHaveBeenCalled();
    });

    it('goes on with the next photo when one cannot be cut', async () => {
        canvas.cutOut.mockRejectedValueOnce(new Error('out of memory'));
        renderPage(Scan);

        await choose(photo('one.jpg'), photo('two.jpg'));

        // the second photo is still cut out, and nothing stays on «Preparing the photo…»
        await waitFor(() => expect(canvas.cutOut).toHaveBeenCalledTimes(2));
        expect(await screen.findByText('Cropped')).toBeInTheDocument();
        expect(
            screen.queryByText('Preparing the photo…'),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Upload' }),
        ).not.toBeDisabled();
    });

    it('reduces the next photo while the server looks for the edges of the first', async () => {
        server.corners.mockImplementation(() => new Promise(() => {}));
        renderPage(Scan);

        await choose(photo('one.jpg'), photo('two.jpg'));

        await waitFor(() =>
            expect(
                canvas.reduceToJpeg.mock.calls.filter(
                    ([, side]) => side === 2000,
                ),
            ).toHaveLength(2),
        );
    });

    it('keeps the crop of the person when the automatic cut finishes after it', async () => {
        let finishAutomatic = (): void => {};
        canvas.cutOut
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        finishAutomatic = () =>
                            resolve({
                                jpeg: new File(['a'], 'automatic.jpg'),
                                plain: new File(['a'], 'automatic-plain.jpg'),
                            });
                    }),
            )
            .mockImplementationOnce(async () => ({
                jpeg: new File(['m'], 'manual.jpg'),
                plain: new File(['m'], 'manual-plain.jpg'),
            }));
        renderPage(Scan);
        await choose(photo('one.jpg'));
        await waitFor(() => expect(canvas.cutOut).toHaveBeenCalledTimes(1));

        await fireEvent.click(screen.getByRole('button', { name: 'Crop' }));
        const crop = await screen.findByRole('dialog', { name: 'Crop' });
        await fireEvent.click(
            within(crop).getByRole('button', { name: 'Whole photo' }),
        );
        await fireEvent.click(
            within(crop).getByRole('button', { name: 'Next' }),
        );
        await waitFor(() => expect(shown()).toBe('blob:manual.jpg'));

        finishAutomatic();
        await settle();

        expect(shown()).toBe('blob:manual.jpg');
    });

    it('hides the bar at the bottom while typing What it is on a phone, at most 200 letters', async () => {
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)',
        );
        renderPage(Scan);
        await choose(photo('one.jpg'));
        const note = await screen.findByLabelText('What it is');

        expect(note).toHaveAttribute('maxlength', '200');
        expect(document.querySelector('footer')).not.toBeNull();

        await fireEvent.focusIn(note);
        expect(document.querySelector('footer')).toBeNull();

        await fireEvent.focusOut(note);
        expect(document.querySelector('footer')).not.toBeNull();
    });

    /** Opens *Crop* on the photo shown and goes on to *Improve*. */
    async function toImprove(): Promise<HTMLElement> {
        await fireEvent.click(screen.getByRole('button', { name: 'Crop' }));
        const crop = await screen.findByRole('dialog', { name: 'Crop' });
        await fireEvent.click(
            within(crop).getByRole('button', { name: 'Next' }),
        );

        return screen.findByRole('dialog', { name: 'Improve' });
    }

    it('crops in two steps, Crop then Improve on the cut sheet, and Done goes back to the pages even with more photos', async () => {
        server.corners.mockResolvedValue(answer({ corners: null }));
        renderPage(Scan);
        await choose(photo('one.jpg'), photo('two.jpg'));
        await waitFor(() =>
            expect(canvas.reduceToJpeg).toHaveBeenCalledTimes(4),
        );

        await fireEvent.click(screen.getByRole('button', { name: 'Page 1' }));
        await fireEvent.click(screen.getByRole('button', { name: 'Crop' }));

        // Crop: the corners and the tools, no improvement
        const crop = await screen.findByRole('dialog', { name: 'Crop' });
        expect(within(crop).getByText('1 of 2')).toBeInTheDocument();
        expect(within(crop).queryByRole('switch')).not.toBeInTheDocument();
        expect(within(crop).queryByRole('slider')).not.toBeInTheDocument();

        await fireEvent.click(
            within(crop).getByRole('button', { name: 'Next' }),
        );

        // Improve: the sheet as it is cut out, with Straighten, Black and white and the strength of Improve
        const improve = await screen.findByRole('dialog', { name: 'Improve' });
        expect(canvas.cutOut).toHaveBeenCalledTimes(1);
        expect(within(improve).getByText('2 of 2')).toBeInTheDocument();
        expect(
            within(improve).getByRole('img', {
                name: 'The cut sheet, as it will be saved',
            }),
        ).toHaveAttribute('src', 'blob:sheet.jpg');
        expect(
            within(improve).getByRole('switch', { name: /Black and white/ }),
        ).toBeChecked();
        expect(
            within(improve).getByRole('slider', { name: 'Improve' }),
        ).toHaveValue('50');
        expect(
            within(improve).getByRole('switch', { name: /Straighten/ }),
        ).toBeDisabled();
        expect(
            within(improve).getByText(
                'Straighten is not available on this phone',
            ),
        ).toBeInTheDocument();

        await fireEvent.click(
            within(improve).getByRole('button', { name: 'Done' }),
        );

        // back to the pages: the second photo does not open
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(canvas.cutOut).toHaveBeenCalledTimes(1);
        expect(screen.getAllByText('Cropped')).toHaveLength(1);
    });

    it('moves the strength of Improve on the cut sheet without cutting the photo again', async () => {
        server.corners.mockResolvedValue(answer({ corners: null }));
        renderPage(Scan);
        await choose(photo('one.jpg'));
        await settle();
        const improve = await toImprove();

        await fireEvent.input(
            within(improve).getByRole('slider', { name: 'Improve' }),
            { target: { value: '80' } },
        );

        expect(await within(improve).findByText('80 %')).toBeInTheDocument();
        await waitFor(() =>
            expect(canvas.improvedJpeg).toHaveBeenLastCalledWith(
                expect.objectContaining({ name: 'plain.jpg' }),
                { angle: 0, enhance: 80, grey: true },
            ),
        );
        await waitFor(() =>
            expect(
                within(improve).getByRole('img', {
                    name: 'The cut sheet, as it will be saved',
                }),
            ).toHaveAttribute('src', expect.not.stringMatching('sheet.jpg')),
        );
        expect(canvas.cutOut).toHaveBeenCalledTimes(1);
    });

    it('goes back to Crop from Improve', async () => {
        server.corners.mockResolvedValue(answer({ corners: null }));
        renderPage(Scan);
        await choose(photo('one.jpg'));
        await settle();
        const improve = await toImprove();

        await fireEvent.click(
            within(improve).getByRole('button', { name: 'Back' }),
        );

        const crop = await screen.findByRole('dialog', { name: 'Crop' });
        expect(within(crop).getByText('1 of 2')).toBeInTheDocument();
    });

    it('asks the server for the edges only with Find the edges', async () => {
        server.corners.mockResolvedValue(answer({ corners: null }));
        renderPage(Scan);
        await choose(photo('one.jpg'));
        await waitFor(() => expect(server.corners).toHaveBeenCalledTimes(1));

        await fireEvent.click(screen.getByRole('button', { name: 'Crop' }));
        const crop = await screen.findByRole('dialog', { name: 'Crop' });
        // opening the crop asks nothing
        expect(server.corners).toHaveBeenCalledTimes(1);

        server.corners.mockResolvedValue(answer({ corners: CORNERS }));
        await fireEvent.click(
            within(crop).getByRole('button', { name: 'Find the edges' }),
        );

        expect(
            await within(crop).findByText(
                'Check the edges of the sheet and, if needed, drag the corners.',
            ),
        ).toBeInTheDocument();
        expect(server.corners).toHaveBeenCalledTimes(2);

        await fireEvent.click(
            within(crop).getByRole('button', { name: 'Next' }),
        );
        await waitFor(() =>
            expect(canvas.cutOut).toHaveBeenCalledWith(
                expect.any(File),
                CORNERS,
                0,
                { enhance: 50, grey: true },
            ),
        );
    });

    it('stops the search for the edges when Crop closes', async () => {
        server.corners.mockResolvedValue(answer({ corners: null }));
        renderPage(Scan);
        await choose(photo('one.jpg'));
        await waitFor(() => expect(server.corners).toHaveBeenCalledTimes(1));
        await fireEvent.click(screen.getByRole('button', { name: 'Crop' }));
        const crop = await screen.findByRole('dialog', { name: 'Crop' });
        let signal: AbortSignal | undefined;
        server.corners.mockImplementation((config) => {
            signal = config.signal ?? undefined;

            return new Promise(() => {});
        });

        await fireEvent.click(
            within(crop).getByRole('button', { name: 'Find the edges' }),
        );
        await waitFor(() => expect(signal).toBeDefined());
        await fireEvent.click(
            within(crop).getByRole('button', { name: 'Cancel' }),
        );

        expect(signal?.aborted).toBe(true);
    });

    it('on the phone has To sort above the title New document, and no Back', () => {
        renderPage(Scan);

        expect(
            screen.getByRole('button', { name: 'To sort' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'New document' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Back' }),
        ).not.toBeInTheDocument();
    });

    it('asks before throwing even one page away, then goes to To sort, also when a page came before', async () => {
        trail.canGoBack.mockReturnValue(true);
        const back = vi
            .spyOn(window.history, 'back')
            .mockImplementation(() => {});
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(Scan);
        await choose(photo('one.jpg'));
        await screen.findByText('Cropped');

        await fireEvent.click(screen.getByRole('button', { name: 'To sort' }));

        const question = await screen.findByRole('dialog', {
            name: 'Throw away 1 page?',
        });
        expect(visit).not.toHaveBeenCalled();
        const keep = within(question).getByRole('button', { name: 'Keep' });
        await waitFor(() => expect(keep).toHaveFocus());

        await fireEvent.click(
            within(question).getByRole('button', { name: 'Throw away' }),
        );
        expect(visit).toHaveBeenCalledWith(expect.stringMatching(/\/to-sort$/));
        expect(back).not.toHaveBeenCalled();
        // the pages stay on screen while the page changes: no empty page in between
        expect(screen.queryByText('No pages')).not.toBeInTheDocument();
    });

    it('goes to To sort at once when the document is empty', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(Scan);

        await fireEvent.click(screen.getByRole('button', { name: 'To sort' }));

        expect(visit).toHaveBeenCalledWith(expect.stringMatching(/\/to-sort$/));
    });

    it('keeps the pages and stays with Keep', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(Scan);
        await choose(photo('a.jpg'), photo('b.jpg'), photo('c.jpg'));
        await waitFor(() =>
            expect(screen.getAllByText('3 pages')).not.toHaveLength(0),
        );

        await fireEvent.click(screen.getByRole('button', { name: 'To sort' }));
        const question = await screen.findByRole('dialog', {
            name: 'Throw away 3 pages?',
        });
        await fireEvent.click(
            within(question).getByRole('button', { name: 'Keep' }),
        );

        await waitFor(() =>
            expect(
                screen.queryByRole('dialog', { name: 'Throw away 3 pages?' }),
            ).not.toBeInTheDocument(),
        );
        expect(screen.getAllByText('3 pages')).not.toHaveLength(0);
        expect(visit).not.toHaveBeenCalled();
    });

    it('on the computer keeps the bar with Back, which goes back to the page it came from', async () => {
        wide(true);
        trail.canGoBack.mockReturnValue(true);
        const back = vi
            .spyOn(window.history, 'back')
            .mockImplementation(() => {});
        renderPage(Scan);

        expect(
            screen.queryByRole('button', { name: 'To sort' }),
        ).not.toBeInTheDocument();
        await fireEvent.click(screen.getByRole('button', { name: 'Back' }));

        expect(back).toHaveBeenCalledTimes(1);
    });

    it('on the computer goes back to the page it came from after throwing the pages away', async () => {
        wide(true);
        trail.canGoBack.mockReturnValue(true);
        const back = vi
            .spyOn(window.history, 'back')
            .mockImplementation(() => {});
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(Scan);
        await choose(photo('one.jpg'));
        await screen.findByText('Cropped');

        await fireEvent.click(screen.getByRole('button', { name: 'Back' }));
        await fireEvent.click(
            within(
                await screen.findByRole('dialog', {
                    name: 'Throw away 1 page?',
                }),
            ).getByRole('button', { name: 'Throw away' }),
        );

        expect(back).toHaveBeenCalledTimes(1);
        expect(visit).not.toHaveBeenCalled();
    });

    it('keeps the bar on a linked phone, with nothing to go back with', () => {
        page.linked = true;
        renderPage(Scan);

        expect(
            screen.getByRole('heading', { name: 'New document' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Back' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'To sort' }),
        ).not.toBeInTheDocument();
    });

    it('lets a linked phone sign in with the password, and stay on the scan', async () => {
        page.linked = true;
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Scan);

        await fireEvent.click(
            screen.getByRole('button', { name: 'Sign in with the password' }),
        );
        expect(post).toHaveBeenCalledWith(expect.stringMatching(/\/logout$/));
    });

    it('uploads after the question, and says what arrived', async () => {
        renderPage(Scan);
        await choose(photo('a.jpg'), photo('b.jpg'), photo('c.jpg'));
        await waitFor(() => expect(canvas.cutOut).toHaveBeenCalledTimes(3));

        await fireEvent.update(
            screen.getByLabelText('What it is'),
            'polizza casa 2026',
        );
        await settle();
        await fireEvent.click(screen.getByRole('button', { name: 'Upload' }));

        const question = await screen.findByRole('dialog', {
            name: 'Upload 3 pages?',
        });
        expect(question).toHaveTextContent(
            '«polizza casa 2026» · 3 pages, in a single PDF',
        );
        const notYet = within(question).getByRole('button', {
            name: 'Not yet',
        });
        await waitFor(() => expect(notYet).toHaveFocus());

        await fireEvent.click(
            within(question).getByRole('button', { name: 'Upload' }),
        );

        expect(
            await screen.findByText(/^Uploaded: 3 pages, /),
        ).toBeInTheDocument();
        const data = server.upload.mock.calls[0][0].data as FormData;
        expect(data.get('note')).toBe('polizza casa 2026');
        expect(screen.getByText('No pages')).toBeInTheDocument();
    });

    it('keeps the pages when the upload fails, and Try again sends them', async () => {
        server.upload.mockRejectedValueOnce(refusal(500, {}));
        renderPage(Scan);
        await choose(photo('a.jpg'));
        await screen.findByText('Cropped');

        await fireEvent.click(screen.getByRole('button', { name: 'Upload' }));
        const question = await screen.findByRole('dialog', {
            name: 'Upload 1 page?',
        });
        await fireEvent.click(
            within(question).getByRole('button', { name: 'Upload' }),
        );

        expect(
            await screen.findByText(
                'The upload did not go through. What was not uploaded is still here.',
            ),
        ).toBeInTheDocument();
        expect(screen.getByText('1 page')).toBeInTheDocument();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Try again' }),
        );

        expect(
            await screen.findByText(/^Uploaded: 1 page, /),
        ).toBeInTheDocument();
        expect(server.upload).toHaveBeenCalledTimes(2);
    });

    it('enters again after a 419 and uploads without losing the pages', async () => {
        server.upload.mockRejectedValueOnce(refusal(419));
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => ({ ok: true, url: `${location.origin}/scan` })),
        );
        renderPage(Scan);
        await choose(photo('a.jpg'), photo('b.jpg'));
        await waitFor(() => expect(canvas.cutOut).toHaveBeenCalledTimes(2));

        await fireEvent.click(screen.getByRole('button', { name: 'Upload' }));
        await fireEvent.click(
            within(
                await screen.findByRole('dialog', { name: 'Upload 2 pages?' }),
            ).getByRole('button', { name: 'Upload' }),
        );

        expect(
            await screen.findByText(/^Uploaded: 2 pages, /),
        ).toBeInTheDocument();
        expect(server.upload).toHaveBeenCalledTimes(2);
    });

    it('lays the pages out side by side on a computer', async () => {
        wide(true);
        renderPage(Scan);

        expect(
            screen.getByRole('button', {
                name: 'Take a photo with the webcam',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'From the computer' }),
        ).toBeInTheDocument();

        await choose(photo('a.jpg'), photo('b.jpg'));

        expect(await screen.findByText('Drag to reorder')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Crop and improve' }),
        ).toBeInTheDocument();
    });
});

describe('Scan, Straighten', () => {
    const SHEET = { width: 1, height: 1, data: new Uint8ClampedArray(4) };

    /** A cut that lays the sheet flat when it is asked to, like the real one. */
    beforeEach(() => {
        straighten.available.mockReturnValue(true);
        server.corners.mockResolvedValue(answer({ corners: null }));
        canvas.cutOut.mockImplementation(
            async (
                _file: Blob,
                _corners: unknown,
                _turns: number,
                _options: unknown,
                flatten?: (sheet: unknown) => Promise<unknown>,
            ) => {
                await flatten?.(SHEET);

                return {
                    jpeg: new File(['s'], 'sheet.jpg', { type: 'image/jpeg' }),
                    plain: new File(['p'], 'plain.jpg', { type: 'image/jpeg' }),
                };
            },
        );
    });

    /** Opens *Crop* on the only photo, takes the whole photo and goes on to *Improve*. */
    async function openImprove(): Promise<HTMLElement> {
        await fireEvent.click(screen.getByRole('button', { name: 'Crop' }));
        const crop = await screen.findByRole('dialog', { name: 'Crop' });
        await fireEvent.click(
            within(crop).getByRole('button', { name: 'Whole photo' }),
        );
        await fireEvent.click(
            within(crop).getByRole('button', { name: 'Next' }),
        );

        return screen.findByRole('dialog', { name: 'Improve' });
    }

    /** Taps *Straighten* and waits for the form to take the value. */
    async function flip(improve: HTMLElement): Promise<void> {
        await fireEvent.click(
            within(improve).getByRole('switch', { name: /Straighten/ }),
        );
        await settle();
    }

    it('is a switch of Improve that can be turned on where the phone can do it, off by default', async () => {
        renderPage(Scan);
        await choose(photo('one.jpg'));
        await settle();

        const improve = await openImprove();
        const toggle = within(improve).getByRole('switch', {
            name: /Straighten/,
        });

        expect(toggle).toBeEnabled();
        expect(toggle).not.toBeChecked();
        expect(
            within(improve).queryByText(
                'Straighten is not available on this phone',
            ),
        ).not.toBeInTheDocument();
    });

    it('says it is not available and cuts without it', async () => {
        straighten.available.mockReturnValue(false);
        renderPage(Scan);
        await choose(photo('one.jpg'));
        await settle();

        const improve = await openImprove();

        expect(
            within(improve).getByRole('switch', { name: /Straighten/ }),
        ).toBeDisabled();
        expect(
            within(improve).getByText(
                'Straighten is not available on this phone',
            ),
        ).toBeInTheDocument();
        expect(canvas.cutOut).toHaveBeenCalledTimes(1);
        expect(canvas.cutOut.mock.calls[0]).toHaveLength(4);
        expect(straighten.dewarp).not.toHaveBeenCalled();
    });

    it('lays the cut sheet flat once when it is on, and not again when it is off and on again', async () => {
        renderPage(Scan);
        await choose(photo('one.jpg'));
        await settle();

        const improve = await openImprove();
        await flip(improve);
        await waitFor(() => expect(straighten.dewarp).toHaveBeenCalledTimes(1));
        await waitFor(() => expect(canvas.cutOut).toHaveBeenCalledTimes(2));

        // off: cut again without it
        await flip(improve);
        await waitFor(() => expect(canvas.cutOut).toHaveBeenCalledTimes(3));
        expect(canvas.cutOut.mock.calls[2]).toHaveLength(4);

        // on again, same corners: the flat sheet in memory is used
        await flip(improve);
        await waitFor(() => expect(canvas.cutOut).toHaveBeenCalledTimes(4));
        await settle();

        expect(straighten.dewarp).toHaveBeenCalledTimes(1);
    });

    it('lays the sheet flat again when the corners change', async () => {
        renderPage(Scan);
        await choose(photo('one.jpg'));
        await settle();

        const improve = await openImprove();
        await flip(improve);
        await waitFor(() => expect(straighten.dewarp).toHaveBeenCalledTimes(1));
        await waitFor(() =>
            expect(
                within(improve).getByRole('button', { name: 'Done' }),
            ).toBeEnabled(),
        );

        await fireEvent.click(
            within(improve).getByRole('button', { name: 'Back' }),
        );
        const crop = await screen.findByRole('dialog', { name: 'Crop' });
        await fireEvent.click(
            within(crop).getByRole('button', { name: 'Rotate' }),
        );
        await fireEvent.click(
            within(crop).getByRole('button', { name: 'Next' }),
        );

        await waitFor(() => expect(straighten.dewarp).toHaveBeenCalledTimes(2));
    });

    it('covers the sheet with a veil while it works, and Back stops it', async () => {
        let signal: AbortSignal | undefined;
        straighten.dewarp.mockImplementation(
            (_sheet, stop) =>
                new Promise((_resolve, reject) => {
                    signal = stop;
                    stop?.addEventListener('abort', () =>
                        reject(new DOMException('Aborted', 'AbortError')),
                    );
                }),
        );
        renderPage(Scan);
        await choose(photo('one.jpg'));
        await settle();

        const improve = await openImprove();
        await flip(improve);

        expect(
            await within(improve).findByText('Straightening…'),
        ).toBeVisible();
        expect(signal?.aborted).toBe(false);

        await fireEvent.click(
            within(improve).getAllByRole('button', { name: 'Back' })[0]!,
        );

        expect(signal?.aborted).toBe(true);
        expect(
            await screen.findByRole('dialog', { name: 'Crop' }),
        ).toBeInTheDocument();
    });

    it('stops it when the page closes', async () => {
        let signal: AbortSignal | undefined;
        straighten.dewarp.mockImplementation(
            (_sheet, stop) =>
                new Promise(() => {
                    signal = stop;
                }),
        );
        const view = renderPage(Scan);
        await choose(photo('one.jpg'));
        await settle();

        const improve = await openImprove();
        await flip(improve);
        await waitFor(() => expect(signal).toBeDefined());

        view.unmount();

        expect(signal?.aborted).toBe(true);
    });

    it('keeps the switches, the slider and Done still while the sheet is laid flat', async () => {
        straighten.dewarp.mockImplementation(() => new Promise(() => {}));
        renderPage(Scan);
        await choose(photo('one.jpg'));
        await settle();

        const improve = await openImprove();
        await flip(improve);
        await within(improve).findByText('Straightening…');

        for (const name of [/Straighten/, /Black and white/]) {
            expect(
                within(improve).getByRole('switch', { name }),
            ).toBeDisabled();
        }
        expect(
            within(improve).getByRole('slider', { name: 'Improve' }),
        ).toBeDisabled();
        expect(
            within(improve).getByRole('button', { name: 'Done' }),
        ).toBeDisabled();
    });

    it('says when the sheet could not be laid flat and stays in Improve', async () => {
        straighten.dewarp.mockRejectedValue(
            new Error('The worker took too long'),
        );
        renderPage(Scan);
        await choose(photo('one.jpg'));
        await settle();

        const improve = await openImprove();
        await flip(improve);

        expect(
            await within(improve).findByText(
                'I could not straighten this sheet.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('dialog', { name: 'Improve' }),
        ).toBeInTheDocument();
    });
});
