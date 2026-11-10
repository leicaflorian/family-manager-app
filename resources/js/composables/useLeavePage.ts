import type { PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Whether a visit shows a page: not a partial reload (`only`, `except`), as the bell and
 * the polls make, nor a prefetch.
 *
 * @param visit - the visit that starts
 * @returns true for a visit that shows a page
 */
function showsPage(
    visit: Pick<PendingVisit, 'only' | 'except' | 'prefetch'>,
): boolean {
    return (
        !visit.prefetch && visit.only.length === 0 && visit.except.length === 0
    );
}

/**
 * The path of an address, without the query.
 *
 * @param url - an address, absolute or from the root
 * @returns the path
 */
function pathOf(url: URL | string): string {
    return new URL(url, window.location.origin).pathname;
}

/**
 * Calls back when the open page goes away: a visit to a page starts (also to the page
 * already open, which fires no `navigate`) or another page is shown (a visit, or the
 * browser's Back and Forward). Partial reloads and prefetches do not count. What the
 * persistent layout keeps open over the page (a menu, a panel) closes here.
 *
 * @param callback - what to do, with the path of the page the visit goes to
 */
export function useLeavePage(callback: (path: string) => void): void {
    let stop: (() => void) | null = null;

    onMounted(() => {
        const offStart = router.on('start', (event): void => {
            if (showsPage(event.detail.visit)) {
                callback(pathOf(event.detail.visit.url));
            }
        });
        const offNavigate = router.on('navigate', (event): void => {
            callback(pathOf(event.detail.page.url));
        });

        stop = (): void => {
            offStart();
            offNavigate();
        };
    });

    onBeforeUnmount(() => stop?.());
}
