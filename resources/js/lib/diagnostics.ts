/**
 * What the browser remembers about itself from the start of the app, so a report of a problem can say what went
 * wrong: the last errors and warnings of the console, the requests that failed, the last pages and the facts of
 * the device. Only short texts are kept (never bodies, form values or props), and nothing here may throw or break
 * the app. The shape is the one `App\Support\FeedbackDiagnostics` accepts on the server.
 */
import { router, usePage } from '@inertiajs/vue3';
import { isIos, isPhone, isStandalone } from '@/lib/devices';

/** The most console entries kept. */
export const MAX_CONSOLE = 50;
/** The most failed requests kept. */
export const MAX_REQUESTS = 20;
/** The most pages kept. */
export const MAX_PAGES = 10;
/** The longest message kept, in characters. */
export const MAX_MESSAGE = 500;
/** The longest path kept, in characters. */
const MAX_PATH = 2048;
/** The stack lines added to an error. */
const STACK_LINES = 4;
/**
 * The largest serialised diagnostics, in bytes: well under what the server reads (256 KB), so the details never
 * make a report fail.
 */
export const MAX_SERIALIZED_BYTES = 65536;
/**
 * Messages that say nothing about a problem, left out of the details:
 * - the browser's ResizeObserver loop, which menus and panels cause and which breaks nothing;
 * - FormKit Pro's warning for a datepicker that starts empty (`""`): it treats the field as without a date, which is
 *   what the forms want. A date that really does not match keeps its warning.
 */
const HARMLESS = [/^ResizeObserver loop /, /^Date \(\) does not match format /];

/** One error or warning of the console. */
export interface ConsoleEntry {
    at: string;
    level: 'error' | 'warn';
    message: string;
    component: string | null;
}

/** One request that failed. */
export interface RequestEntry {
    at: string;
    method: string;
    path: string;
    status: number | null;
    request_id: string | null;
}

/** One page shown. */
export interface PageEntry {
    at: string;
    path: string;
    component: string | null;
}

/** What a report carries about the browser. */
export interface FeedbackDiagnostics {
    client_build: string | null;
    viewport: {
        width: number | null;
        height: number | null;
        dpr: number | null;
        orientation: string | null;
    };
    phone: boolean | null;
    ios: boolean | null;
    standalone: boolean | null;
    theme: string | null;
    locale: string | null;
    online: boolean | null;
    connection: string | null;
    page_age_seconds: number | null;
    component: string | null;
    pages: PageEntry[];
    console: ConsoleEntry[];
    requests: RequestEntry[];
}

/** Where the listeners are installed; tests pass their own. */
export interface DiagnosticsTarget {
    console: Console;
    window: Window;
    /** The `fetch` to wrap (the one of the window when missing). */
    fetch?: typeof fetch;
}

const consoleEntries: ConsoleEntry[] = [];
const requestEntries: RequestEntry[] = [];
const pageEntries: PageEntry[] = [];

/** The method and path of the last Inertia visit that started, which an error response does not carry. */
let lastVisit: { method: string; path: string } = { method: 'GET', path: '' };

/** The stop function of the running installation, so a second start does nothing. */
let running: (() => void) | null = null;

/** The console's own `error`, so recording an error never records it a second time through the wrapper. */
let printError: (...args: unknown[]) => void = (...args) => {
    console.error(...args);
};

/**
 * Adds an entry and keeps only the last ones.
 *
 * @param list - the buffer
 * @param entry - what to add
 * @param max - how many to keep
 */
function push<T>(list: T[], entry: T, max: number): void {
    list.push(entry);
    if (list.length > max) {
        list.splice(0, list.length - max);
    }
}

/**
 * Cuts a text to a length.
 *
 * @param text - the text
 * @param max - the most characters
 * @returns the cut text
 */
function cut(text: string, max: number): string {
    return text.length > max ? text.slice(0, max) : text;
}

/**
 * Turns anything into a short text; circular objects and symbols never throw. A plain object is described by
 * its constructor and keys only, never its values.
 *
 * @param value - what was given to the console
 * @returns the text
 */
function toText(value: unknown): string {
    try {
        if (typeof value === 'string') {
            return value;
        }
        if (value instanceof Error) {
            return errorText(value);
        }
        if (typeof value === 'object' && value !== null) {
            // Only the shape: the values may be response bodies, form models or page props
            const name = value.constructor?.name ?? 'Object';
            const keys = Object.keys(value).slice(0, 10).join(',');

            return `${name}{${keys}}`;
        }

        return String(value);
    } catch {
        return '[unreadable]';
    }
}

/**
 * An error as its name and message, followed by the first lines of its stack (where it was thrown).
 *
 * @param error - the error
 * @returns the text
 */
function errorText(error: Error): string {
    const head = `${error.name}: ${error.message}`;
    // Chrome starts the stack with the name and message again, Firefox and Safari do not
    const frames = (error.stack ?? '')
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '' && line !== head)
        .slice(0, STACK_LINES);

    return [head, ...frames].join('\n');
}

/**
 * A path without the part after `#`: the link of a phone carries its secret there, and it never leaves the
 * browser.
 *
 * @param path - the path, maybe with a fragment
 * @returns the path without it
 */
function withoutFragment(path: string): string {
    const at = path.indexOf('#');

    return at === -1 ? path : path.slice(0, at);
}

/**
 * The page shown now, by component name.
 *
 * @returns the component, or null when there is none yet
 */
function currentComponent(): string | null {
    try {
        return usePage().component || null;
    } catch {
        return null;
    }
}

/**
 * Records a line of the console, unless it is one of the harmless messages.
 *
 * @param level - error or warn
 * @param message - the text
 */
function recordConsole(level: ConsoleEntry['level'], message: string): void {
    if (HARMLESS.some((pattern) => pattern.test(message))) {
        return;
    }

    try {
        push(
            consoleEntries,
            {
                at: new Date().toISOString(),
                level,
                message: cut(message, MAX_MESSAGE),
                component: currentComponent(),
            },
            MAX_CONSOLE,
        );
    } catch {
        // The buffer never breaks the app.
    }
}

/**
 * Records an error that a Vue component threw (`app.config.errorHandler`), and still prints it.
 *
 * @param error - what was thrown
 * @param _instance - the component instance (not kept)
 * @param info - where Vue caught it
 */
export function reportVueError(
    error: unknown,
    _instance: unknown,
    info: string,
): void {
    recordConsole('error', `${toText(error)} (${info})`);
    try {
        printError(error);
    } catch {
        // Nothing to do.
    }
}

/**
 * Records a request that failed.
 *
 * @param entry - the request: its method, path, status (null when it never answered) and `X-Request-Id`
 */
export function recordRequest(entry: {
    method: string;
    path: string;
    status: number | null;
    requestId: string | null;
}): void {
    try {
        push(
            requestEntries,
            {
                at: new Date().toISOString(),
                method: cut(entry.method.toUpperCase(), 10),
                path: cut(withoutFragment(entry.path), MAX_PATH),
                status: entry.status,
                request_id: entry.requestId && cut(entry.requestId, 100),
            },
            MAX_REQUESTS,
        );
    } catch {
        // The buffer never breaks the app.
    }
}

/**
 * Wraps the methods of the console that matter.
 *
 * @param target - the console
 * @returns the function that puts them back
 */
function wrapConsole(target: Console): () => void {
    const originals = { error: target.error, warn: target.warn };
    printError = (...args: unknown[]): void => {
        originals.error.apply(target, args);
    };

    for (const level of ['error', 'warn'] as const) {
        target[level] = (...args: unknown[]): void => {
            recordConsole(level, args.map(toText).join(' '));
            originals[level].apply(target, args);
        };
    }

    return (): void => {
        target.error = originals.error;
        target.warn = originals.warn;
        printError = (...args: unknown[]): void => {
            console.error(...args);
        };
    };
}

/**
 * Wraps `fetch` to keep the requests that fail.
 *
 * @param target - the window whose `fetch` is replaced
 * @param base - the `fetch` that really sends
 * @returns the function that puts the old one back
 */
function wrapFetch(target: Window, base: typeof fetch): () => void {
    const previous = target.fetch.bind(target);

    target.fetch = async (
        input: RequestInfo | URL,
        init?: RequestInit,
    ): Promise<Response> => {
        const method =
            init?.method ?? (input instanceof Request ? input.method : 'GET');
        const path = pathOf(input, target);

        try {
            const response = await base(input, init);

            if (path !== null && response.status >= 400) {
                recordRequest({
                    method,
                    path,
                    status: response.status,
                    requestId: response.headers.get('X-Request-Id'),
                });
            }

            return response;
        } catch (error) {
            // A request the page stopped on purpose (a search typed further) is not a failure
            if (path !== null && !isAbort(error, input, init)) {
                recordRequest({ method, path, status: null, requestId: null });
            }

            throw error;
        }
    };

    return (): void => {
        target.fetch = previous;
    };
}

/**
 * Whether a fetch failed because the page stopped it.
 *
 * @param error - what the fetch threw
 * @param input - what `fetch` received
 * @param init - its options
 * @returns true for an aborted request
 */
function isAbort(
    error: unknown,
    input: RequestInfo | URL,
    init?: RequestInit,
): boolean {
    if (
        init?.signal?.aborted ||
        (input instanceof Request && input.signal.aborted)
    ) {
        return true;
    }

    // A DOMException is not always an Error (jsdom, older Safari), so the name is read on any object
    return (
        typeof error === 'object' &&
        error !== null &&
        (error as { name?: unknown }).name === 'AbortError'
    );
}

/**
 * The path and query of a request to the app, without the host. Requests to other addresses (the home scanner
 * agent, fonts) are not the app's and are not kept.
 *
 * @param input - what `fetch` received
 * @param target - the window, for its address
 * @returns the path with its query, or null for another address
 */
function pathOf(input: RequestInfo | URL, target: Window): string | null {
    try {
        const raw = input instanceof Request ? input.url : String(input);
        const url = new URL(raw, target.location.href);

        return url.origin === target.location.origin
            ? url.pathname + url.search
            : null;
    } catch {
        return null;
    }
}

/**
 * Starts keeping the diagnostics: the console, the uncaught errors, the failed requests (`fetch` and Inertia) and
 * the pages shown. Call it once before Inertia starts, so the first page is kept too; a second call does nothing.
 *
 * @param target - where to install the listeners (the real console and window by default)
 * @returns the function that removes everything again (for the tests)
 */
export function startDiagnostics(
    target: DiagnosticsTarget = { console, window },
): () => void {
    try {
        return install(target);
    } catch {
        // Collecting never stops the app from starting
        return (): void => {};
    }
}

/**
 * Installs the listeners, each one on its own so one that cannot be installed leaves the others working.
 *
 * @param target - where to install them
 * @returns the function that removes them
 */
function install(target: DiagnosticsTarget): () => void {
    if (running) {
        return running;
    }

    const onError = (event: Event): void => {
        const { message, error } = event as ErrorEvent;
        recordConsole('error', `${toText(error ?? message)}`);
    };
    const onRejection = (event: Event): void => {
        recordConsole(
            'error',
            `Unhandled rejection: ${toText((event as PromiseRejectionEvent).reason)}`,
        );
    };
    target.window.addEventListener('error', onError);
    target.window.addEventListener('unhandledrejection', onRejection);

    const offConsole = wrapConsole(target.console);
    let offFetch = (): void => {};
    try {
        offFetch = wrapFetch(
            target.window,
            target.fetch ?? target.window.fetch.bind(target.window),
        );
    } catch {
        // No usable fetch: the rest still works
    }

    const offStart = router.on('start', (event): void => {
        lastVisit = {
            method: event.detail.visit.method,
            path:
                event.detail.visit.url.pathname + event.detail.visit.url.search,
        };
    });
    const offHttp = router.on('httpException', (event): void => {
        const { status, headers } = event.detail.response;
        recordRequest({
            ...lastVisit,
            status,
            requestId: headers?.['x-request-id'] ?? null,
        });
    });
    const offNetwork = router.on('networkError', (): void => {
        recordRequest({ ...lastVisit, status: null, requestId: null });
    });
    const offNavigate = router.on('navigate', (event): void => {
        const { component, url } = event.detail.page;

        push(
            pageEntries,
            {
                at: new Date().toISOString(),
                // Inertia adds the fragment of the address to the page url
                path: cut(withoutFragment(url), MAX_PATH),
                component: component || null,
            },
            MAX_PAGES,
        );
    });

    running = (): void => {
        target.window.removeEventListener('error', onError);
        target.window.removeEventListener('unhandledrejection', onRejection);
        offConsole();
        offFetch();
        offStart();
        offHttp();
        offNetwork();
        offNavigate();
        consoleEntries.length = 0;
        requestEntries.length = 0;
        pageEntries.length = 0;
        running = null;
    };

    return running;
}

/**
 * The buffer and the facts of this moment, in the shape the server accepts.
 *
 * @returns the diagnostics to send with a report
 */
export function collectDiagnostics(): FeedbackDiagnostics {
    const root = document.documentElement;
    const connection = (
        navigator as Navigator & { connection?: { effectiveType?: string } }
    ).connection;

    return {
        client_build: __APP_BUILD__,
        viewport: {
            width: window.innerWidth,
            height: window.innerHeight,
            dpr: window.devicePixelRatio || null,
            orientation: window.screen?.orientation?.type ?? null,
        },
        phone: isPhone(),
        ios: isIos(),
        standalone: isStandalone(),
        theme: root.classList.contains('dark') ? 'dark' : 'light',
        locale: root.lang || null,
        online: navigator.onLine,
        connection: connection?.effectiveType ?? null,
        page_age_seconds: Math.round(performance.now() / 1000),
        component: currentComponent(),
        pages: [...pageEntries],
        console: [...consoleEntries],
        requests: [...requestEntries],
    };
}

/**
 * The diagnostics as JSON within `MAX_SERIALIZED_BYTES`: while they are too large the oldest entry of the longest
 * list goes (console, then requests, then pages on a tie), so the newest, most useful ones stay.
 *
 * @param diagnostics - what `collectDiagnostics()` returned
 * @returns the JSON to send
 */
export function serializeDiagnostics(diagnostics: FeedbackDiagnostics): string {
    const trimmed: FeedbackDiagnostics = {
        ...diagnostics,
        console: [...diagnostics.console],
        requests: [...diagnostics.requests],
        pages: [...diagnostics.pages],
    };
    const encoder = new TextEncoder();
    let json = JSON.stringify(trimmed);

    while (encoder.encode(json).length > MAX_SERIALIZED_BYTES) {
        const lists = [trimmed.console, trimmed.requests, trimmed.pages];
        const longest = lists.reduce((a, b) => (b.length > a.length ? b : a));
        if (longest.length === 0) {
            break;
        }
        longest.shift();
        json = JSON.stringify(trimmed);
    }

    return json;
}
