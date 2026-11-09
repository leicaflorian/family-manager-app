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
import { hasChanges } from '@/lib/unsavedChanges';
import { choose, renderPage, settle } from '@/test/page';
import {
    fakeComposedSheet,
    fakeImprovedJpeg,
    fakeMeasureSide,
    fakeScanAgent,
    resetScanAgent,
    scanAgent,
    tagOf,
} from '@/test/scanAgent';
import Scanner from './Scanner.vue';

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
}));

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

// Starting the agent opens a link of the system: here it says whether the page would ask before leaving
const launched = vi.hoisted(() => ({ asked: [] as boolean[] }));

vi.mock('@/lib/scan/agent', async (original) => ({
    ...(await original<typeof import('@/lib/scan/agent')>()),
    launch: () => {
        launched.asked.push(hasChanges());
    },
}));

const agent = scanAgent;

type Client = ReturnType<typeof http.getClient>;
type Config = Parameters<Client['request']>[0];

const real = http.getClient();
const upload = vi.fn<(config: Config) => Promise<unknown>>();
const visit = vi.fn<(url: string) => void>();
const reload = vi.fn<(options: { only: string[] }) => void>();
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
    resetScanAgent();
    launched.asked = [];
    canvas.measureSide.mockReset().mockImplementation(fakeMeasureSide);
    canvas.improvedJpeg.mockReset().mockImplementation(fakeImprovedJpeg);
    canvas.composedSheet.mockReset().mockImplementation(fakeComposedSheet);
    vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(MAC);
    vi.stubGlobal('fetch', vi.fn(fakeScanAgent));
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
    reload.mockReset();
    vi.spyOn(router, 'reload').mockImplementation(
        reload as unknown as typeof router.reload,
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
        expect(
            screen.getByText('Ready: put the card on the glass'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Scan the front' }),
        ).toBeInTheDocument();

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

    it('turns the page with Rotate only in the PDF: the picture is not made again', async () => {
        await open();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Start scanning' }),
        );
        await screen.findByText('2 pages');

        await fireEvent.click(screen.getByRole('button', { name: 'Rotate' }));

        expect(
            document.querySelector<HTMLElement>('[data-preview] img')!.style
                .transform,
        ).toBe('rotate(90deg)');
        expect(
            screen.getByRole('button', { name: 'Page 1' }).querySelector('img')!
                .style.transform,
        ).toContain('rotate(90deg)');
        expect(canvas.improvedJpeg).not.toHaveBeenCalled();

        await fireEvent.click(screen.getByRole('button', { name: 'Upload' }));
        await waitFor(() => expect(upload).toHaveBeenCalled());
        const pdf = (upload.mock.calls[0][0].data as FormData).get(
            'files[]',
        ) as File;
        expect((await pdf.text()).match(/\/Rotate 90 /g)).toHaveLength(1);
    });

    it('improves the page with the strength of the slider, and gives the same to all the pages', async () => {
        await open();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Start scanning' }),
        );
        await screen.findByText('2 pages');

        await fireEvent.update(
            screen.getByRole('slider', {
                name: 'Improve the light and the ink',
            }),
            '50',
        );
        await waitFor(() => expect(shown()).toBe('blob:sheet-1@0/50/false'));

        await fireEvent.click(
            screen.getByRole('button', {
                name: 'Do the same to all the pages',
            }),
        );
        await waitFor(() => {
            expect(canvas.improvedJpeg).toHaveBeenCalledWith(
                expect.objectContaining({ tag: 'sheet-2' }),
                { angle: 0, enhance: 50, grey: false },
            );
        });
    });

    it('straightens a page that went in crooked, all at once or by hand', async () => {
        agent.sheets = ['crooked-1', 'sheet-2'];
        await open();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Start scanning' }),
        );
        await screen.findByText('2 pages');

        expect(
            screen.getByText('It looks crooked by 1,5°.'),
        ).toBeInTheDocument();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Straighten the crooked one' }),
        );

        await waitFor(() =>
            expect(shown()).toBe('blob:crooked-1@-1.5/0/false'),
        );
        expect(
            screen.queryByRole('button', { name: /Straighten the crooked/ }),
        ).not.toBeInTheDocument();

        await fireEvent.click(screen.getByRole('button', { name: 'Reset' }));
        await waitFor(() => expect(shown()).toBe('blob:crooked-1'));
        await fireEvent.click(
            screen.getByRole('button', { name: 'Find it by itself' }),
        );
        await waitFor(() =>
            expect(shown()).toBe('blob:crooked-1@-1.5/0/false'),
        );
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

describe('Scanner, with the agent closed', () => {
    /** A scan of two sheets, then the agent quits. */
    async function scannedThenClosed(): Promise<void> {
        await open();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Start scanning' }),
        );
        await screen.findByText('2 pages');
        agent.mode = 'down';
        document.dispatchEvent(new Event('visibilitychange'));
        await screen.findByText(/The scanner agent has closed/);
    }

    it('keeps the sides in view, and uploads them without the agent', async () => {
        await scannedThenClosed();

        expect(shown()).toBe('blob:sheet-1');
        expect(
            screen.getByRole('button', { name: 'Page 2' }),
        ).toBeInTheDocument();
        expect(screen.getByAltText('Side put aside')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Add sheets' }),
        ).not.toBeInTheDocument();

        await fireEvent.click(screen.getByRole('button', { name: 'Upload' }));

        await waitFor(() => expect(visit).toHaveBeenCalledWith('/to-sort'));
    });

    it('starts the agent without asking to leave the sides not uploaded', async () => {
        await scannedThenClosed();
        expect(hasChanges()).toBe(true);

        await fireEvent.click(
            screen.getByRole('button', { name: 'Start the agent' }),
        );

        await waitFor(() => expect(launched.asked).toEqual([false]));
        await waitFor(() => expect(hasChanges()).toBe(true), {
            timeout: 2000,
        });
    });

    it('asks the server again for the last version with Try again', async () => {
        agent.mode = 'down';
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(FIREFOX_MAC);
        renderPage(Scanner, { agent_release: null });
        await screen.findByText(/GitHub, where it is published/);

        await fireEvent.click(
            screen.getByRole('button', { name: 'Try again' }),
        );

        expect(reload).toHaveBeenCalledWith({
            only: ['agent_release'],
        });
    });
});

describe('Scanner, a card on a single page', () => {
    /** "On a single page" on, and a card scanned front and back. */
    async function cardScanned(): Promise<void> {
        agent.sheets = ['card-front', 'card-back'];
        await open();
        await fireEvent.click(
            screen.getByRole('switch', { name: 'On a single page' }),
        );
        await screen.findByRole('button', { name: 'Scan the front' });
        await fireEvent.click(
            screen.getByRole('button', { name: 'Scan the front' }),
        );
        await waitFor(() =>
            expect(shown()).toBe('blob:sheet(card-front,card-back)'),
        );
    }

    it('cuts the two sides and puts them on one page, at their real size', async () => {
        await cardScanned();

        expect(screen.getByText('1 page')).toBeInTheDocument();
        expect(screen.getByText('Composed page')).toBeInTheDocument();
        expect(screen.getByText('1 · 85,6 × 54,0 mm')).toBeInTheDocument();
        expect(
            screen.getByText(
                'At their real size: printed at 100 % it is a photocopy.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Add a side' }),
        ).toBeInTheDocument();
    });

    it('corrects the cut of a card by hand, and draws the page again', async () => {
        await cardScanned();
        canvas.composedSheet.mockClear();

        await fireEvent.click(
            screen.getAllByRole('button', {
                name: 'Correct the cut of side 1',
            })[0]!,
        );
        expect(
            await screen.findByText('85,6 × 54,0 mm', {
                selector: '[data-crop-size]',
            }),
        ).toBeInTheDocument();
        await fireEvent.update(
            screen.getByRole('slider', { name: 'Straighten' }),
            '2.5',
        );
        await fireEvent.click(screen.getByRole('button', { name: 'Done' }));

        await waitFor(() => expect(canvas.composedSheet).toHaveBeenCalled());
        const cards = canvas.composedSheet.mock.calls[0][0] as {
            cut: { angle: number };
        }[];
        expect(cards[0]!.cut.angle).toBe(2.5);
    });

    it('turns a card, takes it off the page, and separates the page', async () => {
        await cardScanned();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Turn side 2' }),
        );
        await waitFor(() =>
            expect(screen.getByText('2 · 54,0 × 85,6 mm')).toBeInTheDocument(),
        );

        await fireEvent.click(screen.getByRole('button', { name: 'Separate' }));

        expect(await screen.findByText('2 pages')).toBeInTheDocument();
        expect(shown()).toBe('blob:card-front');
        expect(
            screen.getByRole('button', { name: 'Put on one page (2)' }),
        ).toBeInTheDocument();
    });

    it('rounds the corners of the cards', async () => {
        await cardScanned();
        canvas.composedSheet.mockClear();

        await fireEvent.click(
            screen.getByRole('switch', { name: 'Rounded corners' }),
        );

        await waitFor(() => expect(canvas.composedSheet).toHaveBeenCalled());
        const cards = canvas.composedSheet.mock.calls[0][0] as {
            round: boolean;
        }[];
        expect(cards.every((card) => card.round)).toBe(true);
    });
});

describe('Scanner, what leaves', () => {
    it('says how many sides and what they weigh, and makes a PDF per side', async () => {
        await open();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Start scanning' }),
        );
        await screen.findByText('2 pages');

        expect(document.querySelector('[data-weight]')?.textContent).toMatch(
            /^2 sides · \d+ B$/,
        );

        await fireEvent.click(
            screen.getByRole('switch', { name: 'One PDF per side' }),
        );
        await settle();
        expect(document.querySelector('[data-weight]')?.textContent).toMatch(
            /^2 sides → 2 PDF · \d+ B$/,
        );

        await fireEvent.click(screen.getByRole('button', { name: 'Upload' }));

        await waitFor(() => expect(visit).toHaveBeenCalledWith('/to-sort'));
        expect(upload).toHaveBeenCalledTimes(2);
        const names = upload.mock.calls.map(
            (call) => (call[0].data as FormData).get('name') as string,
        );
        expect(names[0]).toMatch(
            /^Scanner \d{4}-\d{2}-\d{2} \d{2}\.\d{2} 1\.pdf$/,
        );
        expect(names[1]).toMatch(/ 2\.pdf$/);
    });

    it('tells an agent 1.0.0 to update itself by hand', async () => {
        await open();
        expect(
            screen.getByText(/Version 1.0.0 does not update itself/),
        ).toBeInTheDocument();
    });

    it('tells a later agent that it updates itself', async () => {
        agent.version = '1.1.0';
        await open();
        expect(screen.getByText(/It updates itself/)).toBeInTheDocument();
    });
});
