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

const page = vi.hoisted(() => ({ linked: false }));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({
        url: '/scan',
        props: {
            auth: { user: { name: 'Laura Bianchi' } },
            family: { name: 'Bianchi' },
            linked_phone: page.linked,
        },
    }),
}));

// The work on the pixels needs a real canvas: here each step gives a named file, so the test sees which one is shown
const canvas = vi.hoisted(() => ({
    reduceToJpeg: vi.fn(),
    cutOut: vi.fn(),
}));

vi.mock('@/lib/scan/canvas', () => ({
    SEARCH_LONG_SIDE: 1000,
    reduceToJpeg: canvas.reduceToJpeg,
    cutOut: canvas.cutOut,
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

type Client = ReturnType<typeof http.getClient>;
type Config = Parameters<Client['request']>[0];

const real = http.getClient();
/** The server: the corners and the upload, answered by the test. */
const server = {
    corners: vi.fn<() => Promise<unknown>>(),
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
    server.corners.mockReset().mockResolvedValue(answer({ corners: CORNERS }));
    server.upload
        .mockReset()
        .mockResolvedValue(answer({ files: [{ id: 1, name: 'x', size: 1 }] }));
    http.setClient({
        request: (config: Config) =>
            config.url.endsWith('/scan/corners')
                ? server.corners()
                : server.upload(config),
    } as unknown as Client);
});

afterEach(() => {
    http.setClient(real);
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
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
            { enhance: true, grey: true },
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

    it('crops the photos one after the other: «1 of 2», Next, and back to the scan', async () => {
        server.corners.mockResolvedValue(answer({ corners: null }));
        renderPage(Scan);
        await choose(photo('one.jpg'), photo('two.jpg'));
        await waitFor(() =>
            expect(canvas.reduceToJpeg).toHaveBeenCalledTimes(4),
        );

        await fireEvent.click(screen.getByRole('button', { name: 'Page 1' }));
        await fireEvent.click(screen.getByRole('button', { name: 'Crop' }));

        const crop = await screen.findByRole('dialog', { name: 'Crop' });
        expect(within(crop).getByText('1 of 2')).toBeInTheDocument();
        expect(
            within(crop).getByRole('switch', { name: /Black and white/ }),
        ).toBeChecked();
        expect(
            within(crop).getByRole('switch', { name: /Straighten/ }),
        ).toBeDisabled();
        expect(
            within(crop).getByText('Straighten is not available on this phone'),
        ).toBeInTheDocument();

        await fireEvent.click(
            within(crop).getByRole('button', { name: 'Next' }),
        );
        // the second photo opens in a crop of its own, once the first is cut out
        expect(await screen.findByText('2 of 2')).toBeInTheDocument();
        const second = screen.getByRole('dialog', { name: 'Crop' });
        await fireEvent.click(
            within(second).getByRole('button', { name: 'Next' }),
        );

        await waitFor(() =>
            expect(
                screen.queryByRole('dialog', { name: 'Crop' }),
            ).not.toBeInTheDocument(),
        );
        expect(canvas.cutOut).toHaveBeenCalledTimes(2);
        expect(screen.getAllByText('Cropped')).toHaveLength(1);
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
                { enhance: true, grey: true },
            ),
        );
    });

    it('throws one page away at once, and asks for three', async () => {
        renderPage(Scan);
        await choose(photo('one.jpg'));
        await screen.findByText('Cropped');

        await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(await screen.findByText('No pages')).toBeInTheDocument();

        await choose(photo('a.jpg'), photo('b.jpg'), photo('c.jpg'));
        await waitFor(() =>
            expect(screen.getAllByText('3 pages')).not.toHaveLength(0),
        );
        await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        const question = await screen.findByRole('dialog', {
            name: 'Throw away 3 pages?',
        });
        const keep = within(question).getByRole('button', { name: 'Keep' });
        await waitFor(() => expect(keep).toHaveFocus());

        await fireEvent.click(
            within(question).getByRole('button', { name: 'Throw away' }),
        );
        expect(await screen.findByText('No pages')).toBeInTheDocument();
    });

    it('goes back to To sort when the document is empty, but a linked phone stays', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(Scan);

        await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(visit).toHaveBeenCalledWith(expect.stringMatching(/\/to-sort$/));
    });

    it('lets a linked phone sign in with the password, and stay on the scan', async () => {
        page.linked = true;
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Scan);

        await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(visit).not.toHaveBeenCalled();

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
