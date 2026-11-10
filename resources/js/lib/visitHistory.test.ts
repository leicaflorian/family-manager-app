import { router } from '@inertiajs/vue3';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { computed } from 'vue';
import { canGoBack, startVisitHistory } from './visitHistory';

/**
 * The browser history of the tab: one remembered state per entry, as Inertia keeps it
 * in `window.history.state.page.rememberedState` (`router.remember`/`router.restore`).
 */
const tab = {
    entries: [] as Array<Record<string, unknown>>,
    index: 0,
};

/** The navigation type of the document load, as `performance` reports it. */
let navigationType: string | undefined;

let stop: (() => void) | undefined;

/** Opens the tab on a fresh document, with one entry the server rendered (nothing remembered). */
function openTab(type: string | undefined): void {
    navigationType = type;
    tab.entries = [{}];
    tab.index = 0;
}

/**
 * What Inertia fires when the first page is shown (`InitialVisit.fireInitialEvents`):
 * `navigate` of type `initial`.
 */
function firstPage(): void {
    document.dispatchEvent(
        new CustomEvent('inertia:navigate', {
            detail: { page: {}, type: 'initial' },
        }),
    );
}

/**
 * What Inertia does for a visit to another address (`page.set` with `pushState`): a new
 * entry with nothing remembered, `navigate` of type `visit`, then `success`.
 */
function visit(): void {
    tab.entries = [...tab.entries.slice(0, tab.index + 1), {}];
    tab.index += 1;
    document.dispatchEvent(
        new CustomEvent('inertia:navigate', {
            detail: { page: {}, type: 'visit' },
        }),
    );
    answered();
}

/**
 * What Inertia does for a `replace` visit or one to the address already open: the entry
 * is replaced by the new page, with nothing remembered, and only `success` fires.
 */
function replaceVisit(): void {
    tab.entries[tab.index] = {};
    answered();
}

/** `success`, which Inertia fires after every answer of the server. */
function answered(): void {
    document.dispatchEvent(
        new CustomEvent('inertia:success', { detail: { page: {} } }),
    );
}

/**
 * What Inertia fires after the browser's Back or Forward (`handlePopstateEvent`): the
 * entry's page is shown with its remembered state and `navigate` of type `history`.
 */
function browserMoves(steps: number): void {
    tab.index += steps;
    document.dispatchEvent(
        new CustomEvent('inertia:navigate', {
            detail: { page: {}, type: 'history' },
        }),
    );
}

/** A reload of the open entry: a new document, the entry's remembered state wiped by Inertia. */
function reload(): void {
    stop?.();
    navigationType = 'reload';
    tab.entries[tab.index] = {};
    stop = startVisitHistory();
    firstPage();
}

beforeEach(() => {
    window.sessionStorage.clear();
    vi.spyOn(router, 'remember').mockImplementation(
        (data: unknown, key = 'default'): void => {
            tab.entries[tab.index] = { ...tab.entries[tab.index], [key]: data };
        },
    );
    vi.spyOn(router, 'restore').mockImplementation(
        (key = 'default'): unknown => tab.entries[tab.index]?.[key],
    );
    vi.spyOn(window.history, 'length', 'get').mockImplementation(
        (): number => tab.entries.length,
    );
    vi.spyOn(window.performance, 'getEntriesByType').mockImplementation(
        (): PerformanceEntryList =>
            navigationType === undefined
                ? []
                : ([
                      { type: navigationType },
                  ] as unknown as PerformanceEntryList),
    );
});

afterEach(() => {
    stop?.();
    stop = undefined;
    vi.restoreAllMocks();
});

describe('visit history', () => {
    it('cannot go back from the first page', () => {
        openTab('navigate');
        stop = startVisitHistory();
        firstPage();

        expect(canGoBack()).toBe(false);
    });

    it('can go back after a visit', () => {
        openTab('navigate');
        stop = startVisitHistory();
        firstPage();
        visit();

        expect(canGoBack()).toBe(true);
    });

    it('tells a computed that reads it when a visit changes the answer', () => {
        openTab('navigate');
        stop = startVisitHistory();
        firstPage();
        // the top bar reads it while rendering, before Inertia fires navigate
        const back = computed((): boolean => canGoBack());
        expect(back.value).toBe(false);

        visit();

        expect(back.value).toBe(true);
    });

    it('cannot go back after a replace visit on the first page', () => {
        openTab('navigate');
        stop = startVisitHistory();
        firstPage();
        replaceVisit();

        expect(canGoBack()).toBe(false);
    });

    it('follows the browser back and forward through the pages', () => {
        openTab('navigate');
        stop = startVisitHistory();
        firstPage();
        visit();
        visit();

        browserMoves(-1);
        expect(canGoBack()).toBe(true);

        browserMoves(-1);
        expect(canGoBack()).toBe(false);

        browserMoves(2);
        expect(canGoBack()).toBe(true);
    });

    it('keeps the depth of a replaced page for a later return to it', () => {
        openTab('navigate');
        stop = startVisitHistory();
        firstPage();
        replaceVisit();
        visit();

        browserMoves(-1);
        expect(canGoBack()).toBe(false);
    });

    it('adds a step for a visit even when the new page inherits the remembered state', () => {
        openTab('navigate');
        stop = startVisitHistory();
        firstPage();
        // A `preserveState` visit to the same component copies the remembered state.
        tab.entries = [...tab.entries, { ...tab.entries[0] }];
        tab.index = 1;
        document.dispatchEvent(
            new CustomEvent('inertia:navigate', {
                detail: { page: {}, type: 'visit' },
            }),
        );

        expect(canGoBack()).toBe(true);
    });

    it('can still go back after a reload of a later page', () => {
        openTab('navigate');
        stop = startVisitHistory();
        firstPage();
        visit();
        visit();

        reload();

        expect(canGoBack()).toBe(true);
    });

    it('can still go back after returning to the app from another site', () => {
        openTab('navigate');
        stop = startVisitHistory();
        firstPage();
        visit();

        stop();
        navigationType = 'back_forward';
        tab.entries[tab.index] = {};
        stop = startVisitHistory();
        firstPage();

        expect(canGoBack()).toBe(true);
    });

    it('starts again in a new tab, even with the storage of the tab it came from', () => {
        openTab('navigate');
        stop = startVisitHistory();
        firstPage();
        visit();

        stop();
        openTab('navigate');
        stop = startVisitHistory();
        firstPage();

        expect(canGoBack()).toBe(false);
    });

    it('starts at the first page after a reload when the storage fails', () => {
        openTab('navigate');
        stop = startVisitHistory();
        firstPage();
        visit();
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('blocked');
        });
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('blocked');
        });

        expect(() => reload()).not.toThrow();
        expect(canGoBack()).toBe(false);
    });

    it('starts at the first page when the browser reports no navigation type', () => {
        openTab(undefined);
        window.sessionStorage.setItem('fm-visit-depth', '3');
        stop = startVisitHistory();
        firstPage();

        expect(canGoBack()).toBe(false);
    });

    it('cannot go back when the tab has only one entry', () => {
        openTab('reload');
        window.sessionStorage.setItem('fm-visit-depth', '2');
        stop = startVisitHistory();
        firstPage();

        expect(canGoBack()).toBe(false);
    });

    it('stops listening', () => {
        openTab('navigate');
        stop = startVisitHistory();
        firstPage();
        stop();
        stop = undefined;
        visit();

        expect(canGoBack()).toBe(false);
    });
});
