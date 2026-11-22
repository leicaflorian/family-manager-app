import { useEventListener } from '@vueuse/core';

/** What an Escape belongs to, not to the panel: an open menu or list. */
const OPEN_LAYERS = '[role="menu"], [role="listbox"]';

/**
 * Calls `close` on Escape, unless the key was meant for something open inside
 * the page that closes by itself: a menu or a list (the target is inside one,
 * or is a field whose list is open) or one of the extra `layers` (a dialog).
 * Reka's layers listen on the window and never stop the key, so the panel
 * has to look where the key came from.
 *
 * @param close - what to do on a free Escape
 * @param layers - more selectors whose content keeps the Escape for itself
 */
export function useEscapeClose(close: () => void, layers: string[] = []): void {
    const selector = [OPEN_LAYERS, ...layers].join(', ');

    useEventListener(document, 'keydown', (event: KeyboardEvent): void => {
        if (event.key !== 'Escape' || event.defaultPrevented) {
            return;
        }

        const target = event.target;

        if (
            target instanceof Element &&
            (target.closest(selector) !== null ||
                target.matches('[aria-expanded="true"]'))
        ) {
            return;
        }

        close();
    });
}
