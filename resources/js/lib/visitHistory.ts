/**
 * How many pages of the app come before the open one in this tab, so the Back of the
 * top bar knows whether `history.back()` stays in the app or whether it should link to
 * the parent page of the trail instead.
 *
 * The depth follows Inertia's `navigate` event, whose `type` says what happened
 * (@inertiajs/core 3):
 * - `initial`: a document was loaded. A new tab or a typed address starts at 0. After a
 *   reload, or a return from another site, the depth comes from `sessionStorage`,
 *   because Inertia shows the page the server rendered and wipes the remembered state of
 *   the entry on a reload (`InitialVisit.clearRememberedStateOnReload`).
 * - `visit`: a visit pushed a new entry, one step more. The entry may carry the remembered
 *   state of the page before (a `preserveState` visit to the same component), so it is
 *   not read here. The depth goes back to 0 (Back then leads up the trail) when the page
 *   before is outside the layout of the app (sign-in, welcome, connect, scan) or when the
 *   new page is the redirect of a form sent with another method than GET (saving,
 *   deleting, signing in): going back would show a form already sent, or a page that is
 *   gone. The method comes from the `start` of the visit.
 * - `history`: the browser's Back or Forward (popstate) showed an older entry, with the
 *   depth remembered in it.
 *
 * A `replace` visit, or one to the address already open, replaces the entry with a page
 * that remembers nothing and fires no `navigate`: the depth, unchanged, is written again
 * on `success`, unless the entry already has it (polls and partial reloads). Each entry keeps its depth with `router.remember`, and the tab keeps the
 * last one in `sessionStorage`, for a reload.
 */
import type { PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { shallowRef } from 'vue';

/** The key of the depth, in the remembered state of an entry and in `sessionStorage`. */
const KEY = 'fm-visit-depth';

/**
 * The pages of the app before the open one, in this tab. Reactive, because Inertia
 * renders the new page before it fires `navigate`: a template that calls `canGoBack()`
 * renders again when the depth changes.
 */
const depth = shallowRef(0);

/** The page shown, by name; null before the first one. */
let shownPage: string | null = null;

/** The method of the last visit to a page that started. */
let lastMethod = 'get';

/**
 * Whether a visit shows a page: not a partial reload (`only`, `except`), as the bell and
 * the polls make, nor a prefetch.
 *
 * @param visit - the visit that starts
 * @returns true for a visit that shows a page
 */
export function isPageVisit(
    visit: Pick<PendingVisit, 'only' | 'except' | 'prefetch'>,
): boolean {
    return (
        !visit.prefetch && visit.only.length === 0 && visit.except.length === 0
    );
}

/**
 * Reads a depth that may be anything.
 *
 * @param value - the stored value
 * @returns the depth, or null when the value is not a whole number from 0 up
 */
function asDepth(value: unknown): number | null {
    const number = typeof value === 'string' ? Number(value) : value;

    return typeof number === 'number' && Number.isInteger(number) && number >= 0
        ? number
        : null;
}

/**
 * Whether the document was loaded by a reload or by the browser's Back or Forward, the
 * only loads where the tab's stored depth belongs to the open page. Some browsers copy
 * `sessionStorage` into a tab opened from a link, which is a plain `navigate`.
 *
 * @returns true for a `reload` or a `back_forward` load
 */
function isReturn(): boolean {
    const entry = window.performance?.getEntriesByType?.('navigation')[0] as
        | { type?: string }
        | undefined;

    return entry?.type === 'reload' || entry?.type === 'back_forward';
}

/**
 * The depth the tab stored before the document was loaded again.
 *
 * @returns the stored depth, 0 when there is none or the storage cannot be read
 */
function storedDepth(): number {
    try {
        return asDepth(window.sessionStorage.getItem(KEY)) ?? 0;
    } catch {
        return 0;
    }
}

/**
 * Writes the depth in the open entry and in the tab's storage (a failing storage is ignored).
 *
 * @param always - false to leave the entry alone when it already has the depth
 */
function save(always = true): void {
    if (always || asDepth(router.restore(KEY)) !== depth.value) {
        router.remember(depth.value, KEY);
    }

    try {
        window.sessionStorage.setItem(KEY, String(depth.value));
    } catch {
        // Without the storage a reload only starts again at the first page.
    }
}

/**
 * Starts following the visits. Call it once, in `app.ts`, before the first page is shown.
 *
 * @param isAppPage - whether a page, by name, is drawn in the layout of the app (every
 *   page when it is not given)
 * @returns the function that stops following them
 */
export function startVisitHistory(
    isAppPage: (component: string) => boolean = (): boolean => true,
): () => void {
    depth.value = 0;
    shownPage = null;
    lastMethod = 'get';

    const offStart = router.on('start', (event): void => {
        if (isPageVisit(event.detail.visit)) {
            lastMethod = event.detail.visit.method;
        }
    });

    const offNavigate = router.on('navigate', (event): void => {
        const fromApp = shownPage === null || isAppPage(shownPage);

        depth.value = depthAfter(event.detail.type, fromApp);
        shownPage = event.detail.page.component ?? null;
        lastMethod = 'get';
        save();
    });

    const offSuccess = router.on('success', (event): void => {
        shownPage = event.detail.page.component ?? shownPage;
        save(false);
    });

    return (): void => {
        offStart();
        offNavigate();
        offSuccess();
    };
}

/**
 * The depth after a `navigate` of the given type.
 *
 * @param type - what Inertia did: `initial`, `visit` or `history`
 * @param fromApp - the page before is drawn in the layout of the app
 * @returns the new depth
 */
function depthAfter(type: string, fromApp: boolean): number {
    switch (type) {
        case 'visit':
            return fromApp && lastMethod === 'get' ? depth.value + 1 : 0;
        case 'history':
            return asDepth(router.restore(KEY)) ?? depth.value;
        default:
            return isReturn() ? storedDepth() : 0;
    }
}

/**
 * Whether another page of the app comes before the open one in this tab, so that
 * `history.back()` stays in the app. A tab with a single entry never goes back. A
 * template or a computed that calls it follows the depth after each visit.
 *
 * @returns true when Back can use the browser's history
 */
export function canGoBack(): boolean {
    return depth.value > 0 && window.history.length > 1;
}
