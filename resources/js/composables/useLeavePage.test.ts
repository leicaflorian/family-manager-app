import { render } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { useLeavePage } from './useLeavePage';

/**
 * What Inertia fires when a visit starts, with the parts of the visit that count.
 *
 * @param url - where the visit goes
 * @param visit - the options of the visit
 */
function started(
    url: string,
    visit: { only?: string[]; except?: string[]; prefetch?: boolean } = {},
): void {
    document.dispatchEvent(
        new CustomEvent('inertia:start', {
            detail: {
                visit: {
                    url: new URL(url, window.location.origin),
                    method: 'get',
                    only: [],
                    except: [],
                    prefetch: false,
                    ...visit,
                },
            },
        }),
    );
}

/**
 * What Inertia fires after a visit to another address, or after the browser's Back.
 *
 * @param url - the address of the page shown
 * @param type - `visit` or `history`
 */
function navigated(url: string, type: 'visit' | 'history' = 'visit'): void {
    document.dispatchEvent(
        new CustomEvent('inertia:navigate', {
            detail: { page: { url, component: 'Home' }, type },
        }),
    );
}

/**
 * Mounts a component that listens with the composable.
 *
 * @returns the callback and the function that unmounts the component
 */
function listen(): { callback: ReturnType<typeof vi.fn>; unmount: () => void } {
    const callback = vi.fn();
    const Listener = defineComponent({
        setup() {
            useLeavePage(callback);

            return () => h('div');
        },
    });
    const { unmount } = render(Listener);

    return { callback, unmount };
}

describe('useLeavePage', () => {
    it('calls back with the path when a visit to a page starts, even to the page already open', () => {
        const { callback } = listen();

        started('/search?q=Pan');
        started(window.location.pathname);

        expect(callback).toHaveBeenNthCalledWith(1, '/search');
        expect(callback).toHaveBeenNthCalledWith(2, window.location.pathname);
    });

    it('does not call back for a partial reload or a prefetch', () => {
        const { callback } = listen();

        started('/documents', { only: ['bell_notices'] });
        started('/documents', { except: ['files'] });
        started('/documents', { prefetch: true });

        expect(callback).not.toHaveBeenCalled();
    });

    it('calls back when another page is shown, by a visit or by the browser', () => {
        const { callback } = listen();

        navigated('/areas?tab=all');
        navigated('/search?q=Pan', 'history');

        expect(callback).toHaveBeenNthCalledWith(1, '/areas');
        expect(callback).toHaveBeenNthCalledWith(2, '/search');
    });

    it('stops listening when the component goes away', () => {
        const { callback, unmount } = listen();

        unmount();
        started('/areas');
        navigated('/areas');

        expect(callback).not.toHaveBeenCalled();
    });
});
