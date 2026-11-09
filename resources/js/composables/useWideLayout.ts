import type { Ref } from 'vue';
import { onBeforeUnmount, ref } from 'vue';

/**
 * The window of the wide layout of the scan pages (a tablet or a computer, with the panels side by side). The
 * only place that writes this condition: every page that changes with it asks `useWideLayout()`.
 */
export const WIDE_QUERY = '(min-width: 900px) and (min-height: 560px)';

/**
 * Whether the window is wide and tall enough for the wide layout, kept right while the window changes.
 *
 * @returns true in the wide layout; false without `matchMedia`
 */
export function useWideLayout(): Ref<boolean> {
    const wide = ref(false);

    if (
        typeof window === 'undefined' ||
        typeof window.matchMedia !== 'function'
    ) {
        return wide;
    }

    const media = window.matchMedia(WIDE_QUERY);
    const follow = (event: { matches: boolean }): void => {
        wide.value = event.matches;
    };

    wide.value = media.matches;
    media.addEventListener('change', follow);
    onBeforeUnmount(() => media.removeEventListener('change', follow));

    return wide;
}
