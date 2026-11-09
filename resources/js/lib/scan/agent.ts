// The calls to the agent of the scanner (its own repository, family-manager-agent): a program on this computer that
// commands the scanner with the driver of the system. The page reaches it on 127.0.0.1, at a fixed port, which the
// security rule of Directus must allow (CONTENT_SECURITY_POLICY_DIRECTIVES__CONNECT_SRC).

/** Where the agent answers. The port is written also in `server.go` of the agent and in the rule of Directus. */
export const AGENT_URL = 'http://127.0.0.1:47115';

/** The link that starts the agent: the system knows it once the agent has been opened one time. */
export const AGENT_LINK = 'familymanageragent://open';

/** How often the page asks the agent for its status, in milliseconds. */
export const STATUS_EVERY = 2000;

/** The list of scanners is asked again every this many status calls (and at every call while none is chosen). */
export const LIST_EVERY = 3;

/**
 * Where the *Scansiona* page is with the agent: `searching` (asking), `missing` (not installed: download it),
 * `pending` (the question is open on the computer), `denied`, `connected`, `stopped` (it answered and no longer
 * does: start it), `unsupported` (a phone, a tablet, Linux).
 */
export type Phase =
    | 'searching'
    | 'missing'
    | 'pending'
    | 'denied'
    | 'connected'
    | 'stopped'
    | 'unsupported';

/** A call waits this long: at its start the agent may take some seconds to open the scanner. */
const TIMEOUT = 12_000;

/**
 * What the page sends with its requests (`api=2`) and an agent that knows the choices answers with: the scanner,
 * the resolution, the colour and where the sheets come from. An older agent ignores it and answers without `api`.
 */
export const API = 2;

/** `opening`: the scanner asked is being opened. Only an agent that knows the choices says it. */
export type ScannerState = 'ready' | 'empty' | 'off' | 'opening';
/** Where the sheets come from: the feeder, or the glass. */
export type Source = 'feeder' | 'flatbed';
export type Colour = 'colour' | 'grey';
/** One of the scanners the computer sees, on the cable or on the network. */
export type Scanner = {
    id: string;
    name: string;
    connection: 'usb' | 'network';
};
/** What a scan is asked with. */
export type Settings = {
    scanner: string;
    source: Source;
    resolution: number;
    colour: Colour;
};
/**
 * `api` is there from the agent that knows the choices. With the scanner asked open it says also what it has:
 * `source` is where it reads from now, `sources`, `resolutions` and `colours` what it can.
 */
export type AgentStatus = {
    version: string;
    os: string;
    scanner: ScannerState;
    api?: number;
    source?: Source;
    sources?: Source[];
    resolutions?: number[];
    colours?: Colour[];
};

export type ScanState = 'idle' | 'scanning' | 'done' | 'error';
/** Why a scan stopped: scanner off, feeder empty, a sheet stuck, anything else. */
export type ScanFailure = 'off' | 'empty' | 'jam' | 'scanner';
export type AgentPage = { id: string; size: number };
/** `session` changes when the agent starts again: the ids of its sides start again from 1. */
export type AgentPages = {
    session: string;
    state: ScanState;
    error: ScanFailure | null;
    message: string;
    pages: AgentPage[];
};

/**
 * Why a call did not get its answer:
 * - `unreachable`: nobody answers (the agent is not running or not installed, or the browser forbids the call);
 * - `pending`: the agent is asking on the computer whether this site may use the scanner;
 * - `denied`: the owner answered no;
 * - `scanning`: a scan is running;
 * - `settings`: the scanner does not have what the scan was asked with;
 * - `failed`: anything else.
 */
export type AgentFailure =
    | 'unreachable'
    | 'pending'
    | 'denied'
    | 'scanning'
    | 'settings'
    | 'failed';

export class AgentError extends Error {
    constructor(public reason: AgentFailure) {
        super(reason);
    }
}

export function failureOf(error: unknown): AgentFailure {
    return error instanceof AgentError ? error.reason : 'failed';
}

async function call(path: string, method = 'GET'): Promise<Response> {
    const control = new AbortController();
    const timer = setTimeout(() => control.abort(), TIMEOUT);
    let response: Response;
    try {
        response = await fetch(AGENT_URL + path, {
            method,
            cache: 'no-store',
            signal: control.signal,
        });
    } catch {
        throw new AgentError('unreachable');
    } finally {
        clearTimeout(timer);
    }
    if (response.ok) return response;

    const reason = await response.json().then(
        (body) => body?.error,
        () => null,
    );
    if (reason === 'origin_pending') throw new AgentError('pending');
    if (reason === 'origin_denied') throw new AgentError('denied');
    if (reason === 'scanning') throw new AgentError('scanning');
    if (reason === 'settings') throw new AgentError('settings');
    throw new AgentError('failed');
}

/** True when the agent knows the choices: the page shows them, and the agent opens only the scanner it is asked. */
export function hasChoices(status: AgentStatus | null): boolean {
    return (status?.api ?? 0) >= API;
}

function query(values: Record<string, string | number | undefined>): string {
    const parts = new URLSearchParams({ api: String(API) });
    for (const [name, value] of Object.entries(values)) {
        if (value !== undefined && value !== '') parts.set(name, String(value));
    }
    return `?${parts}`;
}

/** The scanners the computer sees. The agent opens none of them for the list. */
export async function scanners(): Promise<Scanner[]> {
    const body = await (await call('/scanners')).json();
    return Array.isArray(body?.scanners) ? body.scanners : [];
}

/**
 * How the scanner is. With an agent that knows the choices it is the scanner asked, which the agent opens, set
 * on the source asked; without a scanner the agent opens none. An older agent answers of the one it chose.
 */
export async function status(
    scanner?: string,
    source?: Source,
): Promise<AgentStatus> {
    return (await call(`/status${query({ scanner, source })}`)).json();
}

/**
 * Feeds the sheets of the feeder, or reads the one on the glass: the sides are queued after the ones the agent
 * already has. Without settings it is a scan of an older agent, which chooses by itself.
 */
export async function scan(settings?: Settings): Promise<void> {
    await call(settings ? `/scan${query(settings)}` : '/scan', 'POST');
}

export async function pages(): Promise<AgentPages> {
    return (await call('/pages')).json();
}

/** The JPEG of a side. With `fetch` and not with `<img src>`, which does not say which site is asking. */
export async function page(id: string): Promise<Blob> {
    return (await call(`/pages/${encodeURIComponent(id)}`)).blob();
}

/** Deletes every side from the temporary folder of the agent. */
export async function clear(): Promise<void> {
    await call('/pages', 'DELETE');
}

/** True when version `installed` comes before `latest` (`1.0.0` before `1.0.1`). */
export function isOlder(installed: string, latest: string): boolean {
    const numbers = (version: string) =>
        version.split('.').map((part) => Number.parseInt(part, 10) || 0);
    const [a, b] = [numbers(installed), numbers(latest)];
    for (let index = 0; index < Math.max(a.length, b.length); index++) {
        const difference = (a[index] ?? 0) - (b[index] ?? 0);
        if (difference !== 0) return difference < 0;
    }
    return false;
}

/**
 * Asks the system to start the agent. With a link the system does not know nothing happens in Chrome; Firefox
 * would replace the page with an error, so there the agent is started by hand.
 */
export function canLaunch(browser: { userAgent: string } = navigator): boolean {
    return !/Firefox/i.test(browser.userAgent);
}

export function launch(): void {
    window.location.assign(AGENT_LINK);
}

/** True when Chrome was told not to let this site reach the programs of the computer. */
export async function localAccessDenied(): Promise<boolean> {
    for (const name of ['loopback-network', 'local-network-access']) {
        try {
            const permission = await navigator.permissions.query({
                name: name as PermissionName,
            });
            if (permission.state === 'denied') return true;
        } catch {
            // a browser that does not know this permission does not ask for it
        }
    }
    return false;
}
