/**
 * Helper for the tests that depend on the room on the page: jsdom does not
 * measure anything, so the widths are set by the test and the ResizeObserver
 * is told when the room changes, as a browser would do.
 */
import { afterEach, vi } from 'vite-plus/test';
import { nextTick } from 'vue';

/** The callbacks of the ResizeObservers of the page. */
const watchers: ResizeObserverCallback[] = [];

/**
 * Replaces the ResizeObserver with one that `layOut` can call. To be called
 * before mounting the component.
 */
export function watchLayout(): void {
    vi.stubGlobal(
        'ResizeObserver',
        class {
            /** Keeps the callback to call it from `layOut`. */
            constructor(callback: ResizeObserverCallback) {
                watchers.push(callback);
            }
            /** The room is reported by `layOut`. */
            observe(): void {}
            /** Nothing to stop. */
            unobserve(): void {}
            /** Nothing to disconnect. */
            disconnect(): void {}
        },
    );
}

/**
 * Lays the page out: every element `width` px wide and every room `room` px,
 * then tells the observers the room changed.
 *
 * @param room - the width of the room, `clientWidth`
 * @param width - the width of every element, `offsetWidth`
 */
export async function layOut(room: number, width = 100): Promise<void> {
    vi.spyOn(HTMLElement.prototype, 'offsetWidth', 'get').mockReturnValue(
        width,
    );
    vi.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(room);
    for (const watcher of watchers) {
        watcher([], {} as ResizeObserver);
    }
    await nextTick();
}

afterEach(() => {
    watchers.length = 0;
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});
