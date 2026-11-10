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
 *   not read here.
 * - `history`: the browser's Back or Forward (popstate) showed an older entry, with the
 *   depth remembered in it.
 *
 * A `replace` visit, or one to the address already open, replaces the entry with a page
 * that remembers nothing and fires no `navigate`: the depth, unchanged, is written again
 * on `success`. Each entry keeps its depth with `router.remember`, and the tab keeps the
 * last one in `sessionStorage`, for a reload.
 */
import { router } from '@inertiajs/vue3';

/** The key of the depth, in the remembered state of an entry and in `sessionStorage`. */
const KEY = 'fm-visit-depth';

/** The pages of the app before the open one, in this tab. */
let depth = 0;

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

/** Writes the depth in the open entry and in the tab's storage (a failing storage is ignored). */
function save(): void {
    router.remember(depth, KEY);

    try {
        window.sessionStorage.setItem(KEY, String(depth));
    } catch {
        // Without the storage a reload only starts again at the first page.
    }
}

/**
 * Starts following the visits. Call it once, in `app.ts`, before the first page is shown.
 *
 * @returns the function that stops following them
 */
export function startVisitHistory(): () => void {
    depth = 0;

    const offNavigate = router.on('navigate', (event): void => {
        depth = depthAfter(event.detail.type);
        save();
    });

    const offSuccess = router.on('success', (): void => {
        save();
    });

    return (): void => {
        offNavigate();
        offSuccess();
    };
}

/**
 * The depth after a `navigate` of the given type.
 *
 * @param type - what Inertia did: `initial`, `visit` or `history`
 * @returns the new depth
 */
function depthAfter(type: string): number {
    switch (type) {
        case 'visit':
            return depth + 1;
        case 'history':
            return asDepth(router.restore(KEY)) ?? depth;
        default:
            return isReturn() ? storedDepth() : 0;
    }
}

/**
 * Whether another page of the app comes before the open one in this tab, so that
 * `history.back()` stays in the app. A tab with a single entry never goes back.
 *
 * @returns true when Back can use the browser's history
 */
export function canGoBack(): boolean {
    return depth > 0 && window.history.length > 1;
}
