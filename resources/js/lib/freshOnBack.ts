/**
 * Asks the server again for a page that the browser's Back or Forward shows after
 * something was saved.
 *
 * Inertia shows an older entry of the history with the props it had then, without a
 * request (`navigate` of type `history`, @inertiajs/core 3). After a payment, Back to the
 * Home showed the due date still to pay. So each address remembers how many saves (visits
 * with another method than GET: saving, paying, deleting) had happened when its page last
 * came from the server; shown again by Back after a later save, it is reloaded once
 * (`router.reload` keeps the scroll and the state of the page). Without a save in between, Back stays
 * instant.
 */
import type { PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { isPageVisit } from './visitHistory';

/**
 * Starts following the visits of the tab.
 *
 * @returns a function that stops listening
 */
export function startFreshOnBack(): () => void {
    /** How many saves have succeeded in this tab. */
    let saves = 0;

    /** The saves counted when the page of each address last came from the server. */
    const loaded = new Map<string, number>();

    /** The visit that started last, to read its method and whether it shows a page. */
    let current: Pick<
        PendingVisit,
        'method' | 'only' | 'except' | 'prefetch'
    > | null = null;

    const listeners = [
        router.on('start', ({ detail }) => {
            current = detail.visit;
        }),
        router.on('success', ({ detail }) => {
            if (current === null) {
                return;
            }
            if (current.method !== 'get') {
                saves += 1;
            }
            // A partial reload (the bell, a poll) brings only some props
            if (isPageVisit(current)) {
                loaded.set(detail.page.url, saves);
            }
            current = null;
        }),
        router.on('navigate', ({ detail }) => {
            const url = detail.page.url;

            if (detail.type !== 'history') {
                if (detail.type === 'initial') {
                    loaded.set(url, saves);
                }

                return;
            }

            if ((loaded.get(url) ?? 0) < saves) {
                loaded.set(url, saves);
                router.reload();
            }
        }),
    ];

    return (): void => {
        for (const stop of listeners) {
            stop();
        }
    };
}
