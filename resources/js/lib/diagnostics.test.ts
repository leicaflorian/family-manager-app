import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    collectDiagnostics,
    MAX_CONSOLE,
    MAX_MESSAGE,
    MAX_PAGES,
    MAX_SERIALIZED_BYTES,
    recordRequest,
    reportVueError,
    serializeDiagnostics,
    startDiagnostics,
} from './diagnostics';

let stop: (() => void) | undefined;

/** A console whose methods only count calls. */
function fakeConsole(): Console {
    return {
        error: vi.fn(),
        warn: vi.fn(),
        log: vi.fn(),
    } as unknown as Console;
}

/** What Inertia fires when a page is shown. */
function navigate(component: string, url: string, type = 'visit'): void {
    document.dispatchEvent(
        new CustomEvent('inertia:navigate', {
            detail: { page: { component, url }, type },
        }),
    );
}

beforeEach(() => {
    document.documentElement.lang = 'it';
    document.documentElement.classList.remove('dark');
});

afterEach(() => {
    stop?.();
    stop = undefined;
});

describe('diagnostics', () => {
    it('keeps console.error and console.warn and still prints them', () => {
        const fake = fakeConsole();
        const original = fake.error;
        stop = startDiagnostics({ console: fake, window });

        fake.error('boom', 42);
        fake.warn('careful');

        expect(original).toHaveBeenCalledWith('boom', 42);
        const { console: entries } = collectDiagnostics();
        expect(entries.map((e) => [e.level, e.message])).toEqual([
            ['error', 'boom 42'],
            ['warn', 'careful'],
        ]);
        expect(Number.isNaN(Date.parse(entries[0].at))).toBe(false);
    });

    it('keeps an uncaught error and an unhandled rejection', () => {
        stop = startDiagnostics({ console: fakeConsole(), window });

        window.dispatchEvent(
            new ErrorEvent('error', { message: 'x is not a function' }),
        );
        const rejection = new Event('unhandledrejection');
        Object.defineProperty(rejection, 'reason', {
            value: new Error('nope'),
        });
        window.dispatchEvent(rejection);

        const messages = collectDiagnostics().console.map((e) => e.message);
        expect(messages[0]).toContain('x is not a function');
        expect(messages[1]).toContain('nope');
    });

    it('keeps only the last 50 entries, each cut to 500 characters', () => {
        const fake = fakeConsole();
        stop = startDiagnostics({ console: fake, window });

        for (let i = 0; i < 60; i++) {
            fake.error(`n${i}`);
        }
        fake.error('x'.repeat(900));

        const entries = collectDiagnostics().console;
        expect(entries).toHaveLength(MAX_CONSOLE);
        expect(entries.at(-1)?.message).toHaveLength(MAX_MESSAGE);
        expect(entries[0].message).toBe('n11');
    });

    it('keeps a failed fetch with its request id and ignores one that succeeds', async () => {
        const fake = vi.fn((input: RequestInfo | URL) =>
            Promise.resolve(
                typeof input === 'string' && input.includes('bad')
                    ? new Response('', {
                          status: 500,
                          headers: { 'X-Request-Id': 'abc-123' },
                      })
                    : new Response('ok', { status: 200 }),
            ),
        );
        stop = startDiagnostics({
            console: fakeConsole(),
            window,
            fetch: fake as unknown as typeof fetch,
        });

        await window.fetch('/fine');
        await window.fetch('/bad?x=1', { method: 'POST' });

        expect(collectDiagnostics().requests).toMatchObject([
            {
                method: 'POST',
                path: '/bad?x=1',
                status: 500,
                request_id: 'abc-123',
            },
        ]);
    });

    it('keeps a rejected fetch without status', async () => {
        const fake = vi.fn(() => Promise.reject(new TypeError('offline')));
        stop = startDiagnostics({
            console: fakeConsole(),
            window,
            fetch: fake as unknown as typeof fetch,
        });

        await expect(window.fetch('/down')).rejects.toThrow('offline');

        expect(collectDiagnostics().requests).toMatchObject([
            { method: 'GET', path: '/down', status: null, request_id: null },
        ]);
    });

    it('keeps failed Inertia visits with the request id', () => {
        stop = startDiagnostics({ console: fakeConsole(), window });
        document.dispatchEvent(
            new CustomEvent('inertia:start', {
                detail: {
                    visit: {
                        method: 'post',
                        url: new URL('/documents?a=1', window.location.origin),
                    },
                },
            }),
        );
        document.dispatchEvent(
            new CustomEvent('inertia:httpException', {
                detail: {
                    response: {
                        status: 500,
                        headers: { 'x-request-id': 'rid-9' },
                        data: '<html>',
                    },
                },
            }),
        );

        expect(collectDiagnostics().requests).toMatchObject([
            {
                method: 'POST',
                path: '/documents?a=1',
                status: 500,
                request_id: 'rid-9',
            },
        ]);
    });

    it('keeps the last 10 pages', () => {
        stop = startDiagnostics({ console: fakeConsole(), window });

        navigate('home', '/', 'initial');
        for (let i = 0; i < 12; i++) {
            navigate(`page-${i}`, `/p/${i}`);
        }

        const { pages } = collectDiagnostics();
        expect(pages).toHaveLength(MAX_PAGES);
        expect(pages.at(-1)).toMatchObject({
            component: 'page-11',
            path: '/p/11',
        });
    });

    it('never throws on a circular object or a symbol', () => {
        const fake = fakeConsole();
        stop = startDiagnostics({ console: fake, window });
        const circular: Record<string, unknown> = {};
        circular.self = circular;

        expect(() => fake.error(circular, Symbol('s'))).not.toThrow();
        expect(() => reportVueError(circular, null, 'render')).not.toThrow();
        expect(() =>
            recordRequest({
                method: 'GET',
                path: 'x'.repeat(5000),
                status: 404,
                requestId: null,
            }),
        ).not.toThrow();

        const { console: entries, requests } = collectDiagnostics();
        expect(entries[0].message).toContain('Symbol(s)');
        expect(entries.length).toBe(2);
        expect(requests[0].path).toHaveLength(2048);
    });

    it('has the facts of the device and the build', () => {
        stop = startDiagnostics({ console: fakeConsole(), window });
        document.documentElement.classList.add('dark');

        const facts = collectDiagnostics();

        expect(facts).toMatchObject({
            client_build: __APP_BUILD__,
            locale: 'it',
            theme: 'dark',
            online: true,
            phone: false,
            ios: false,
            standalone: false,
        });
        expect(facts.viewport.width).toBe(window.innerWidth);
        expect(typeof facts.page_age_seconds).toBe('number');
    });

    it('records a Vue error once', () => {
        const fake = fakeConsole();
        stop = startDiagnostics({ console: fake, window });

        reportVueError(new Error('render failed'), null, 'render');

        expect(collectDiagnostics().console).toHaveLength(1);
        expect(fake.error).toBeDefined();
    });

    it('describes an object by its keys, never its values', () => {
        const fake = fakeConsole();
        stop = startDiagnostics({ console: fake, window });

        fake.error({ password: 'secret' });

        const message = collectDiagnostics().console[0].message;
        expect(message).toContain('password');
        expect(message).not.toContain('secret');
    });

    it('does not throw when the window has no fetch, and still records the console', () => {
        const fake = fakeConsole();
        const bare = Object.create(window, {
            fetch: { value: undefined },
        }) as Window;

        expect(() => {
            stop = startDiagnostics({ console: fake, window: bare });
        }).not.toThrow();

        fake.error('still here');
        expect(collectDiagnostics().console[0].message).toBe('still here');
    });

    it('never keeps the part of an address after #, where the phone link has its secret', async () => {
        const fake = vi.fn(() =>
            Promise.resolve(new Response('', { status: 500 })),
        );
        stop = startDiagnostics({
            console: fakeConsole(),
            window,
            fetch: fake as unknown as typeof fetch,
        });

        navigate('Scan', '/scan#d=secret');
        navigate('Scan', '/scan?x=1#d=secret');
        await window.fetch('/scan/corners#d=secret', { method: 'POST' });
        recordRequest({
            method: 'GET',
            path: '/connect#d=secret',
            status: 500,
            requestId: null,
        });

        const { pages, requests } = collectDiagnostics();
        expect(pages.map((p) => p.path)).toEqual(['/scan', '/scan?x=1']);
        expect(requests.map((r) => r.path)).toEqual([
            '/scan/corners',
            '/connect',
        ]);
    });

    it('does not keep a fetch the page stopped on purpose', async () => {
        const fake = vi.fn(() =>
            Promise.reject(new DOMException('aborted', 'AbortError')),
        );
        stop = startDiagnostics({
            console: fakeConsole(),
            window,
            fetch: fake as unknown as typeof fetch,
        });
        const controller = new AbortController();
        controller.abort();

        await expect(
            window.fetch('/search/suggest?q=typed', {
                signal: controller.signal,
            }),
        ).rejects.toThrow('aborted');
        await expect(window.fetch('/search/suggest?q=more')).rejects.toThrow(
            'aborted',
        );

        expect(collectDiagnostics().requests).toEqual([]);
    });

    it('keeps only the requests to the app, never those to other addresses', async () => {
        const fake = vi.fn(() =>
            Promise.resolve(new Response('', { status: 404 })),
        );
        stop = startDiagnostics({
            console: fakeConsole(),
            window,
            fetch: fake as unknown as typeof fetch,
        });

        await window.fetch('http://127.0.0.1:47115/scanners');
        await window.fetch('https://fonts.example.com/font.woff2');
        await window.fetch(`${window.location.origin}/mine`);

        expect(collectDiagnostics().requests.map((r) => r.path)).toEqual([
            '/mine',
        ]);
    });

    it('adds the first lines of the stack to an error', () => {
        const fake = fakeConsole();
        stop = startDiagnostics({ console: fake, window });
        const error = new TypeError('x is undefined');
        error.stack = [
            'TypeError: x is undefined',
            '    at pay (Pay.vue:12:3)',
            '    at click (Button.vue:4:1)',
            '    at a (a.js:1:1)',
            '    at b (b.js:1:1)',
            '    at c (c.js:1:1)',
            '    at d (d.js:1:1)',
        ].join('\n');

        fake.error(error);

        const message = collectDiagnostics().console[0].message;
        expect(message).toContain('TypeError: x is undefined');
        expect(message).toContain('at pay (Pay.vue:12:3)');
        expect(message).toContain('at click (Button.vue:4:1)');
        expect(message).not.toContain('at d (d.js:1:1)');
        expect(message.match(/TypeError: x is undefined/g)).toHaveLength(1);
    });

    it('serialises within the budget, dropping the oldest entries first', () => {
        const fake = fakeConsole();
        stop = startDiagnostics({ console: fake, window });
        for (let i = 0; i < 50; i++) {
            fake.error(`e${i} ${'é'.repeat(600)}`);
        }
        for (let i = 0; i < 20; i++) {
            recordRequest({
                method: 'GET',
                path: `/r${i}?${'q'.repeat(3000)}`,
                status: 500,
                requestId: null,
            });
        }

        const json = serializeDiagnostics(collectDiagnostics());

        expect(new TextEncoder().encode(json).length).toBeLessThanOrEqual(
            MAX_SERIALIZED_BYTES,
        );
        const parsed = JSON.parse(json) as ReturnType<
            typeof collectDiagnostics
        >;
        expect(parsed.console.at(-1)?.message.startsWith('e49 ')).toBe(true);
        expect(parsed.console.length).toBeLessThan(50);
        expect(parsed.locale).toBe('it');
    });

    it('serialises small diagnostics as they are', () => {
        stop = startDiagnostics({ console: fakeConsole(), window });
        const facts = collectDiagnostics();

        expect(JSON.parse(serializeDiagnostics(facts))).toEqual(facts);
    });
});
