import { http, router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
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
import { choose, renderPage, settle } from '@/test/page';
import Scanner from './Scanner.vue';

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
}));

/** A blob with a name the test can read back: jsdom has no way to look inside the pictures. */
const tagged = (tag: string): Blob =>
    Object.assign(new Blob([tag]), { tag }) as Blob;
const tagOf = (blob: Blob): string => (blob as Blob & { tag: string }).tag;

// The work on the pixels needs a real canvas: here a side made again is the original with what was asked written in
const canvas = vi.hoisted(() => ({
    remakeSide: vi.fn(),
    blank: new WeakSet<object>(),
}));

vi.mock('@/lib/scan/canvas', () => ({
    remakeSide: canvas.remakeSide,
    SEARCH_LONG_SIDE: 1000,
    canvasBackend: {
        load: async (one: { jpeg: Blob }) => ({
            jpeg: new TextEncoder().encode(`jpeg-${tagOf(one.jpeg)}`),
            width: 10,
            height: 14,
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

vi.mock('@/lib/scan/blank', () => ({
    isBlank: (image: { blank: boolean }) => image.blank,
}));

/** The agent, as the test sets it. */
const agent = {
    /** `reachable`: answers; `pending`: asks the owner; `denied`: refused; `down`: nobody there. */
    mode: 'reachable' as 'reachable' | 'pending' | 'denied' | 'down',
    version: '1.0.0',
    scanner: 'ready',
    scanners: [{ id: 'sp', name: 'SP-1120N', connection: 'usb' }],
    pages: [] as { id: string; blob: Blob }[],
    state: 'idle',
    error: null as string | null,
    /** What a scan brings: the tags of its sides, the blank ones are `blank`. */
    sheets: ['sheet-1', 'blank', 'sheet-2'],
    calls: [] as string[],
};

/** One call to the agent, answered as it would. */
async function fakeAgent(url: string, init?: RequestInit): Promise<unknown> {
    const { pathname, search } = new URL(url);
    const method = init?.method ?? 'GET';
    agent.calls.push(`${method} ${pathname}${search}`);
    const reply = (body: unknown, ok = true) => ({
        ok,
        json: async () => body,
    });

    if (agent.mode === 'down') throw new TypeError('Failed to fetch');
    if (agent.mode === 'pending')
        return reply({ error: 'origin_pending' }, false);
    if (agent.mode === 'denied')
        return reply({ error: 'origin_denied' }, false);

    if (pathname === '/status') {
        return reply({
            version: agent.version,
            os: 'darwin',
            scanner: agent.scanner,
            api: 2,
            source: 'feeder',
            sources: ['feeder', 'flatbed'],
            resolutions: [150, 200, 300],
            colours: ['colour', 'grey'],
        });
    }

    if (pathname === '/scanners') return reply({ scanners: agent.scanners });

    if (pathname === '/scan' && method === 'POST') {
        agent.pages = agent.sheets.map((tag, index) => ({
            id: String(index + 1),
            blob: tagged(tag),
        }));
        agent.state = agent.error ? 'error' : 'done';

        return reply({});
    }

    if (pathname === '/pages' && method === 'DELETE') {
        agent.pages = [];

        return reply({});
    }

    if (pathname === '/pages') {
        return reply({
            session: 's1',
            state: agent.state,
            error: agent.error,
            message: '',
            pages: agent.pages.map((one) => ({ id: one.id, size: 1 })),
        });
    }

    const side = agent.pages.find((one) => pathname === `/pages/${one.id}`);

    return {
        ok: Boolean(side),
        json: async () => ({}),
        blob: async () => side?.blob,
    };
}

type Client = ReturnType<typeof http.getClient>;
type Config = Parameters<Client['request']>[0];

const real = http.getClient();
const upload = vi.fn<(config: Config) => Promise<unknown>>();
const visit = vi.fn<(url: string) => void>();
const release = { version: '1.2.0', mac: true, windows: true };

const MAC =
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Chrome/130 Safari/537.36';
const FIREFOX_MAC =
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:130.0) Gecko/20100101 Firefox/130.0';

/** Makes the window wide, as a computer. */
function wide(on: boolean): void {
    vi.stubGlobal('matchMedia', (query: string) => ({
        matches: on && query === WIDE_QUERY,
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
    }));
}

/** The picture in the large view. */
const shown = (): string | null =>
    document.querySelector('[data-preview] img')?.getAttribute('src') ?? null;

beforeEach(() => {
    Object.assign(agent, {
        mode: 'reachable',
        version: '1.0.0',
        scanner: 'ready',
        pages: [],
        state: 'idle',
        error: null,
        sheets: ['sheet-1', 'blank', 'sheet-2'],
        calls: [],
    });
    canvas.remakeSide
        .mockReset()
        .mockImplementation(
            async (
                original: Blob,
                options: { turns: number; enhance: boolean; grey: boolean },
            ) =>
                tagged(
                    `${tagOf(original)}+${options.turns}${options.enhance ? '+improved' : ''}${options.grey ? '+grey' : ''}`,
                ),
        );
    vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(MAC);
    vi.stubGlobal('fetch', vi.fn(fakeAgent));
    vi.stubGlobal(
        'createImageBitmap',
        vi.fn(async (blob: Blob) => ({
            width: 10,
            height: 14,
            blank: blob !== undefined && tagOf(blob) === 'blank',
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
    wide(true);
    upload.mockReset().mockResolvedValue({
        status: 200,
        data: JSON.stringify({ files: [{ id: 1, name: 'x', size: 1 }] }),
        headers: {},
    });
    http.setClient({
        request: (config: Config) => upload(config),
    } as unknown as Client);
    visit.mockReset();
    vi.spyOn(router, 'visit').mockImplementation(
        visit as unknown as typeof router.visit,
    );
});

afterEach(() => {
    http.setClient(real);
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

/** Opens the page with the agent answering and waits for the choices. */
async function open(props: Record<string, unknown> = {}): Promise<void> {
    renderPage(Scanner, { agent_release: release, ...props });
    await screen.findByRole('button', { name: 'Start scanning' });
    // the list of the scanners and what the chosen one has come after the page
    await waitFor(() => {
        expect(
            screen.getByRole('button', { name: 'Resolution' }),
        ).toBeInTheDocument();
    });
}

describe('Scanner, finding the agent', () => {
    it('tells a phone, a tablet or Linux that the scanner is used from a Mac or a PC', async () => {
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(
            'Mozilla/5.0 (Linux; Android 15; Pixel 9) Mobile',
        );

        renderPage(Scanner);

        expect(
            await screen.findByText(/used from a Mac or a Windows PC/),
        ).toBeInTheDocument();
        expect(fetch).not.toHaveBeenCalled();
    });

    it('says when the agent is not installed, with the download for this system and the steps', async () => {
        agent.mode = 'down';
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(FIREFOX_MAC);

        renderPage(Scanner, { agent_release: release });

        expect(
            await screen.findByText('The scanner agent does not answer'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', {
                name: 'Download the agent for Mac (version 1.2.0)',
            }),
        ).toHaveAttribute('href', '/scanner/agent/mac');
        expect(
            screen.getByText(/Move it into the Applications folder/),
        ).toBeInTheDocument();
    });

    it('says GitHub does not answer when the last version is not known, and offers no download', async () => {
        agent.mode = 'down';
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(FIREFOX_MAC);

        renderPage(Scanner, { agent_release: null });

        expect(
            await screen.findByText(
                /GitHub, where it is published, does not answer/,
            ),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: /Download/ }),
        ).not.toBeInTheDocument();
    });

    it('waits for the answer to the question on the computer, then goes on by itself', async () => {
        agent.mode = 'pending';
        renderPage(Scanner, { agent_release: release });

        expect(await screen.findByText(/Answer Allow/)).toBeInTheDocument();

        agent.mode = 'reachable';
        document.dispatchEvent(new Event('visibilitychange'));

        expect(
            await screen.findByRole('button', { name: 'Start scanning' }),
        ).toBeInTheDocument();
    });

    it('explains a refusal and tries again', async () => {
        agent.mode = 'denied';
        renderPage(Scanner);

        expect(
            await screen.findByText(/The agent does not accept this site/),
        ).toBeInTheDocument();

        agent.mode = 'reachable';
        await fireEvent.click(
            screen.getByRole('button', { name: 'Try again' }),
        );

        expect(
            await screen.findByRole('button', { name: 'Start scanning' }),
        ).toBeInTheDocument();
    });

    it('says the agent has closed when it stops answering, and keeps the sides', async () => {
        await open();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Start scanning' }),
        );
        await screen.findByText('2 pages');

        agent.mode = 'down';
        document.dispatchEvent(new Event('visibilitychange'));

        expect(
            await screen.findByText(/The scanner agent has closed/),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/The sides already scanned are still here/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Start the agent' }),
        ).toBeInTheDocument();
    });

    it('warns of an older agent, which works all the same', async () => {
        await open();

        expect(
            await screen.findByText(
                /The agent installed is version 1.0.0: there is 1.2.0/,
            ),
        ).toBeInTheDocument();
    });

    it('does not warn when the agent is the last one', async () => {
        agent.version = '1.2.0';
        await open();

        expect(
            screen.queryByText(/The agent installed is version/),
        ).not.toBeInTheDocument();
    });
});

describe('Scanner, the choices', () => {
    beforeEach(() => localStorage.clear());

    it('chooses the only scanner there is, in grey and at the lightest resolution', async () => {
        await open();

        await waitFor(() => {
            expect(
                screen.getByRole('button', { name: 'Scanner' }),
            ).toHaveTextContent('SP-1120N · USB');
        });
        expect(
            screen.getByRole('button', { name: 'Resolution' }),
        ).toHaveTextContent('Light (150 dots)');
        expect(screen.getByRole('button', { name: 'In grey' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        expect(screen.getByRole('button', { name: 'Feeder' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        expect(screen.getByText('about 62 sides')).toBeInTheDocument();
    });

    it('scans with what was chosen', async () => {
        await open();
        await screen.findByRole('button', { name: 'Resolution' });
        await choose('Resolution', 'Normal (200 dots)');
        await userEvent.click(
            screen.getByRole('button', { name: 'In colour' }),
        );
        await settle();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Start scanning' }),
        );
        await screen.findByText('2 pages');

        const call = agent.calls.find((one) => one.startsWith('POST /scan'))!;
        expect(call).toContain('scanner=sp');
        expect(call).toContain('resolution=200');
        expect(call).toContain('colour=colour');
        expect(call).toContain('source=feeder');
    });

    it('asks for colour at 300 dots from the glass for "On a single page", and puts them back', async () => {
        await open();
        await screen.findByRole('button', { name: 'Resolution' });

        await fireEvent.click(
            screen.getByRole('switch', { name: 'On a single page' }),
        );
        await waitFor(() => {
            expect(
                screen.getByRole('button', { name: 'Resolution' }),
            ).toHaveTextContent('High (300 dots)');
        });
        expect(
            screen.getByRole('button', { name: 'In colour' }),
        ).toHaveAttribute('aria-pressed', 'true');
        expect(screen.getByRole('button', { name: 'Glass' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );

        await fireEvent.click(
            screen.getByRole('switch', { name: 'On a single page' }),
        );
        await waitFor(() => {
            expect(
                screen.getByRole('button', { name: 'Resolution' }),
            ).toHaveTextContent('Light (150 dots)');
        });
        expect(screen.getByRole('button', { name: 'In grey' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
    });
});

describe('Scanner, a scan', () => {
    it('shows the sides that come, puts the blank one aside and counts the pages', async () => {
        await open();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Start scanning' }),
        );

        expect(await screen.findByText('2 pages')).toBeInTheDocument();
        expect(shown()).toBe('blob:sheet-1');
        expect(
            screen.getByText('Sides put aside: they do not go in the PDF'),
        ).toBeInTheDocument();
        expect(screen.getByAltText('Side put aside')).toHaveAttribute(
            'src',
            'blob:blank',
        );
        expect(
            screen.getByRole('button', { name: 'Add sheets' }),
        ).toBeInTheDocument();
    });

    it('puts a blank side back among the pages', async () => {
        await open();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Start scanning' }),
        );
        await screen.findByText('2 pages');

        await fireEvent.click(screen.getByRole('button', { name: 'Put back' }));

        expect(await screen.findByText('3 pages')).toBeInTheDocument();
    });

    it('takes a page out of the PDF with Remove, and shows the next', async () => {
        await open();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Start scanning' }),
        );
        await screen.findByText('2 pages');

        await fireEvent.click(
            screen.getByRole('button', { name: 'Remove this page' }),
        );

        expect(await screen.findByText('1 page')).toBeInTheDocument();
        expect(shown()).toBe('blob:sheet-2');
        expect(screen.getAllByAltText('Side put aside')).toHaveLength(2);
    });

    it('turns the page with Rotate, from the picture as it arrived', async () => {
        await open();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Start scanning' }),
        );
        await screen.findByText('2 pages');

        await fireEvent.click(screen.getByRole('button', { name: 'Rotate' }));

        await waitFor(() => expect(shown()).toBe('blob:sheet-1+1'));
        expect(canvas.remakeSide).toHaveBeenLastCalledWith(
            expect.objectContaining({ tag: 'sheet-1' }),
            { turns: 1, enhance: false, grey: false },
        );
    });

    it('improves the page, and gives the same to all the pages', async () => {
        await open();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Start scanning' }),
        );
        await screen.findByText('2 pages');

        await fireEvent.click(
            screen.getByRole('switch', {
                name: 'Improve the light and the ink',
            }),
        );
        await waitFor(() => expect(shown()).toBe('blob:sheet-1+0+improved'));

        await fireEvent.click(
            screen.getByRole('button', {
                name: 'Do the same to all the pages',
            }),
        );
        await waitFor(() => {
            expect(canvas.remakeSide).toHaveBeenCalledWith(
                expect.objectContaining({ tag: 'sheet-2' }),
                { turns: 0, enhance: true, grey: false },
            );
        });
    });

    it.each([
        [
            'empty',
            'There are no sheets in the feeder: put them in and try again.',
        ],
        ['jam', 'A sheet is stuck'],
    ])('says why a scan stopped: %s', async (code, text) => {
        agent.error = code;
        agent.sheets = ['sheet-1'];
        await open();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Start scanning' }),
        );

        expect(await screen.findByText(new RegExp(text))).toBeInTheDocument();
        expect(
            screen.getByText(
                /The sides already scanned are still here|there are no/i,
            ),
        ).toBeInTheDocument();
    });

    it('says the scanner is off, and does not scan', async () => {
        agent.scanner = 'off';
        renderPage(Scanner, { agent_release: release });

        expect(
            await screen.findByText('Scanner off or not plugged in'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Start scanning' }),
        ).toBeDisabled();
    });
});

describe('Scanner, Upload', () => {
    /** A scan of two sheets, and the name written. */
    async function scanned(name = ''): Promise<void> {
        await open();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Start scanning' }),
        );
        await screen.findByText('2 pages');

        if (name) {
            await fireEvent.update(
                screen.getByLabelText('Name of the document'),
                name,
            );
            await settle();
        }
    }

    it('sends one PDF of the pages that stay, as the scanner’s, with the name, then empties the agent and goes back to To sort', async () => {
        await scanned('estratto conto');

        await fireEvent.click(screen.getByRole('button', { name: 'Upload' }));

        await waitFor(() => expect(visit).toHaveBeenCalledWith('/to-sort'));
        const data = upload.mock.calls[0][0].data as FormData;
        expect(data.get('source')).toBe('scanner');
        expect(data.get('name')).toBe('estratto conto');
        const pdf = data.get('files[]') as File;
        expect(pdf.type).toBe('application/pdf');
        expect((await pdf.text()).match(/\/Type \/Page\b/g)).toHaveLength(2);
        expect(agent.calls).toContain('DELETE /pages');
    });

    it('leaves the name out when none is written, so the server names it with the moment', async () => {
        await scanned();

        await fireEvent.click(screen.getByRole('button', { name: 'Upload' }));

        await waitFor(() => expect(upload).toHaveBeenCalled());
        expect((upload.mock.calls[0][0].data as FormData).has('name')).toBe(
            false,
        );
    });

    it('keeps the pages and says why when the upload does not go through', async () => {
        await scanned();
        upload.mockRejectedValue(
            Object.assign(new Error('failed'), {
                response: {
                    status: 422,
                    data: JSON.stringify({
                        errors: { files: ['The file is too large.'] },
                    }),
                    headers: {},
                },
            }),
        );

        await fireEvent.click(screen.getByRole('button', { name: 'Upload' }));

        expect(
            await screen.findByText(/The file is too large/),
        ).toBeInTheDocument();
        expect(screen.getByText('2 pages')).toBeInTheDocument();
        expect(visit).not.toHaveBeenCalled();
        expect(agent.calls).not.toContain('DELETE /pages');
    });

    it('has no Upload before a scan', async () => {
        await open();

        expect(
            screen.queryByRole('button', { name: 'Upload' }),
        ).not.toBeInTheDocument();
        expect(
            within(document.body).queryByText('No pages'),
        ).toBeInTheDocument();
    });
});
