/**
 * The agent of the scanner as the tests of *Scan the sheets* see it: a fake `fetch` that answers the calls to
 * `http://127.0.0.1:47115` as the agent would, with what the test set in `scanAgent`, and blobs with a tag the
 * test can read back, since jsdom cannot look inside a picture.
 */

/**
 * A blob with a name the test can read back.
 *
 * @param tag - the name
 * @returns the blob
 */
export const tagged = (tag: string): Blob =>
    Object.assign(new Blob([tag]), { tag }) as Blob;

/**
 * The name of a blob made by `tagged`.
 *
 * @param blob - the blob
 * @returns its name
 */
export const tagOf = (blob: Blob): string =>
    (blob as Blob & { tag?: string }).tag ?? '';

/** What the fake agent is and holds. */
export type FakeScanAgent = {
    /** `reachable`: answers; `pending`: asks the owner; `denied`: refused; `down`: nobody there. */
    mode: 'reachable' | 'pending' | 'denied' | 'down';
    version: string;
    scanner: string;
    scanners: { id: string; name: string; connection: string }[];
    /** The sides it holds. */
    pages: { id: string; blob: Blob }[];
    state: 'idle' | 'scanning' | 'done' | 'error';
    error: string | null;
    /** What a scan brings: the tags of its sides, the blank ones are `blank`. */
    sheets: string[];
    /** A scan already started from another tab: `POST /scan` answers `scanning`. */
    busy: boolean;
    /** How many times `/pages` still says `scanning` before the scan is over. */
    scanningFor: number;
    /** Every call, as «GET /status?…». */
    calls: string[];
    /** Holds a call until the promise it gives ends; null to answer at once. */
    hold: (call: string) => Promise<void> | null;
};

/** The fake agent of the test. */
export const scanAgent: FakeScanAgent = {
    mode: 'reachable',
    version: '1.0.0',
    scanner: 'ready',
    scanners: [{ id: 'sp', name: 'SP-1120N', connection: 'usb' }],
    pages: [],
    state: 'idle',
    error: null,
    sheets: ['sheet-1', 'blank', 'sheet-2'],
    busy: false,
    scanningFor: 0,
    calls: [],
    hold: () => null,
};

/** Puts the agent back as it is at the start of a test. */
export function resetScanAgent(): void {
    Object.assign(scanAgent, {
        mode: 'reachable',
        version: '1.0.0',
        scanner: 'ready',
        scanners: [{ id: 'sp', name: 'SP-1120N', connection: 'usb' }],
        pages: [],
        state: 'idle',
        error: null,
        sheets: ['sheet-1', 'blank', 'sheet-2'],
        busy: false,
        scanningFor: 0,
        calls: [],
        hold: () => null,
    } satisfies FakeScanAgent);
}

/** The sides a scan brings, as the agent keeps them. */
function sheetsAsPages(): { id: string; blob: Blob }[] {
    return scanAgent.sheets.map((tag, index) => ({
        id: String(index + 1),
        blob: tagged(tag),
    }));
}

/**
 * One call to the agent, answered as it would.
 *
 * @param url - the address called
 * @param init - the method
 * @returns what `fetch` would give
 */
export async function fakeScanAgent(
    url: string,
    init?: RequestInit,
): Promise<unknown> {
    const { pathname, search } = new URL(url);
    const method = init?.method ?? 'GET';
    const call = `${method} ${pathname}${search}`;
    scanAgent.calls.push(call);
    await scanAgent.hold(call);
    const reply = (
        body: unknown,
        ok = true,
    ): { ok: boolean; json: () => Promise<unknown> } => ({
        ok,
        json: async () => body,
    });

    if (scanAgent.mode === 'down') throw new TypeError('Failed to fetch');
    if (scanAgent.mode === 'pending')
        return reply({ error: 'origin_pending' }, false);
    if (scanAgent.mode === 'denied')
        return reply({ error: 'origin_denied' }, false);

    if (pathname === '/status') {
        return reply({
            version: scanAgent.version,
            os: 'darwin',
            scanner: scanAgent.scanner,
            api: 2,
            source: 'feeder',
            sources: ['feeder', 'flatbed'],
            resolutions: [150, 200, 300],
            colours: ['colour', 'grey'],
        });
    }

    if (pathname === '/scanners') {
        return reply({ scanners: scanAgent.scanners });
    }

    if (pathname === '/scan' && method === 'POST') {
        if (scanAgent.busy) return reply({ error: 'scanning' }, false);

        scanAgent.pages = sheetsAsPages();
        scanAgent.state = scanAgent.error ? 'error' : 'done';

        return reply({});
    }

    if (pathname === '/pages' && method === 'DELETE') {
        scanAgent.pages = [];

        return reply({});
    }

    if (pathname === '/pages') {
        if (scanAgent.state === 'scanning') {
            if (scanAgent.scanningFor > 0) scanAgent.scanningFor--;
            else {
                scanAgent.pages = sheetsAsPages();
                scanAgent.state = 'done';
            }
        }

        return reply({
            session: 's1',
            state: scanAgent.state,
            error: scanAgent.error,
            message: '',
            pages: scanAgent.pages.map((one) => ({ id: one.id, size: 1 })),
        });
    }

    const side = scanAgent.pages.find((one) => pathname === `/pages/${one.id}`);

    return {
        ok: Boolean(side),
        json: async () => ({}),
        blob: async () => side?.blob,
    };
}

/**
 * What `measureSide` finds in a side, read from its tag: `crooked` went in by 1,5°, `card` holds a card.
 *
 * @param blob - the side
 * @returns what the canvas would measure
 */
export async function fakeMeasureSide(blob: Blob): Promise<{
    millimetre: number;
    skew: number | null;
    found: {
        box: { x: number; y: number; width: number; height: number };
        angle: number;
    } | null;
    small: boolean;
}> {
    const tag = tagOf(blob);

    return {
        millimetre: 10,
        skew: tag.includes('crooked') ? 1.5 : null,
        found: tag.includes('card')
            ? { box: { x: 10, y: 10, width: 856, height: 540 }, angle: 0 }
            : null,
        small: tag.includes('card'),
    };
}

/**
 * A side made again: the original with what was asked written in its tag («sheet-1@-1.5/50/true»).
 *
 * @param original - the side of the scanner
 * @param wanted - the angle, *Improve* and grey
 * @returns the side made
 */
export async function fakeImprovedJpeg(
    original: Blob,
    wanted: { angle: number; enhance: number; grey: boolean },
): Promise<Blob> {
    return tagged(
        `${tagOf(original)}@${wanted.angle}/${wanted.enhance}/${wanted.grey}`,
    );
}

/**
 * A composed page: the tags of its cards, in order («sheet(card-front,card-back)»).
 *
 * @param cards - the cards
 * @returns the page as the canvas would draw it
 */
export async function fakeComposedSheet(cards: { image: Blob }[]): Promise<{
    original: Blob;
    blob: Blob;
    width: number;
    height: number;
}> {
    const page = tagged(
        `sheet(${cards.map((card) => tagOf(card.image)).join(',')})`,
    );

    return { original: page, blob: page, width: 2100, height: 2970 };
}
