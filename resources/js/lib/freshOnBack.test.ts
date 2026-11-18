import { router } from '@inertiajs/vue3';
import type { MockInstance } from 'vite-plus/test';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { startFreshOnBack } from './freshOnBack';

let stop: (() => void) | undefined;
let reload: MockInstance<typeof router.reload>;

/**
 * Fires an Inertia event on the document, as Inertia does.
 *
 * @param name - the event, without `inertia:`
 * @param detail - its detail
 */
function fire(name: string, detail: Record<string, unknown>): void {
    document.dispatchEvent(new CustomEvent(`inertia:${name}`, { detail }));
}

/** The first page of the tab, at `url`. */
function firstPage(url: string): void {
    fire('navigate', { page: { url }, type: 'initial' });
}

/**
 * A visit that ends on the page at `url`: `start`, `navigate` (a new entry, unless the
 * address is the open one) and `success`.
 *
 * @param url - the page shown at the end (the redirect, for a form sent)
 * @param method - the method of the visit
 * @param options - `only` for a partial reload, `pushed` false for the address already open
 */
function visit(
    url: string,
    method = 'get',
    { only = [] as string[], pushed = true } = {},
): void {
    fire('start', {
        visit: { method, only, except: [], prefetch: false },
    });
    if (pushed) {
        fire('navigate', { page: { url }, type: 'visit' });
    }
    fire('success', { page: { url } });
}

/** The browser's Back or Forward, showing the entry at `url` with its old props. */
function back(url: string): void {
    fire('navigate', { page: { url }, type: 'history' });
}

beforeEach(() => {
    reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
    stop = startFreshOnBack();
});

afterEach(() => {
    stop?.();
    reload.mockRestore();
});

describe('A page shown again by Back', () => {
    it('stays as it was when nothing was saved since', () => {
        firstPage('/dashboard');
        visit('/budget/planned/expense/8/occurrences/2026-10-23');
        back('/dashboard');

        expect(reload).not.toHaveBeenCalled();
    });

    it('asks the server again after a save', () => {
        firstPage('/dashboard');
        visit('/budget/planned/expense/8/occurrences/2026-10-23');
        visit('/budget/planned/expense/8/occurrences/2026-10-23', 'post', {
            pushed: false,
        });
        back('/dashboard');

        expect(reload).toHaveBeenCalledOnce();
    });

    it('asks only once: Back and Forward again show what it got', () => {
        firstPage('/dashboard');
        visit('/budget/planned/expense/8/occurrences/2026-10-23');
        visit('/budget/planned/expense/8/occurrences/2026-10-23', 'post', {
            pushed: false,
        });
        back('/dashboard');
        // the reload of the dashboard answers
        visit('/dashboard', 'get', { pushed: false });
        // Forward to the due date, already shown after the payment
        back('/budget/planned/expense/8/occurrences/2026-10-23');
        back('/dashboard');

        expect(reload).toHaveBeenCalledOnce();
    });

    it('asks again for a page shown before a reload of the tab', () => {
        firstPage('/budget/movements');
        visit('/budget/movements/3', 'delete', { pushed: true });
        back('/dashboard');

        expect(reload).toHaveBeenCalledOnce();
    });

    it('does not count a partial reload as the page asked again', () => {
        firstPage('/dashboard');
        visit('/budget/planned/expense/8/occurrences/2026-10-23');
        visit('/budget/planned/expense/8/occurrences/2026-10-23', 'post', {
            pushed: false,
        });
        // the bell asks only its notices while on the due date
        visit('/dashboard', 'get', { only: ['notices'], pushed: false });
        back('/dashboard');

        expect(reload).toHaveBeenCalledOnce();
    });

    it('stops listening', () => {
        stop?.();
        stop = undefined;
        firstPage('/dashboard');
        visit('/a', 'post');
        back('/dashboard');

        expect(reload).not.toHaveBeenCalled();
    });
});
