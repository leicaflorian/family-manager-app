import { useMediaQuery } from '@vueuse/core';
import type { ComputedRef, Ref } from 'vue';
import { computed, onBeforeUnmount, ref } from 'vue';

/** The three layouts of the app shell. */
export type LayoutMode = 'phone' | 'tablet' | 'computer';

/** The window of the tablet layout (and up). Written only here. */
export const TABLET_QUERY = '(min-width: 768px) and (min-height: 560px)';

/** The window of the computer layout. Written only here. */
export const COMPUTER_QUERY = '(min-width: 1024px) and (min-height: 560px)';

/** The window of a wide computer, where a page has room for one more column. Written only here. */
export const WIDE_COMPUTER_QUERY =
    '(min-width: 1440px) and (min-height: 560px)';

/**
 * The layout of the window: phone (below 768 px wide or below 560 px tall), tablet (768 to 1023 px) or
 * computer (from 1024 px), kept right while the window changes. The only place that writes these
 * conditions: every page or component that changes with the layout asks `useLayoutMode()`, never
 * `matchMedia` or `min-[…]:` classes. The columns inside a page stay with `useWideLayout()`.
 *
 * @returns the current layout; 'phone' without `matchMedia`
 */
export function useLayoutMode(): Ref<LayoutMode> {
    const mode = ref<LayoutMode>('phone');

    if (
        typeof window === 'undefined' ||
        typeof window.matchMedia !== 'function'
    ) {
        return mode;
    }

    const tablet = window.matchMedia(TABLET_QUERY);
    const computer = window.matchMedia(COMPUTER_QUERY);
    const update = (): void => {
        mode.value = computer.matches
            ? 'computer'
            : tablet.matches
              ? 'tablet'
              : 'phone';
    };

    update();
    tablet.addEventListener('change', update);
    computer.addEventListener('change', update);
    onBeforeUnmount(() => {
        tablet.removeEventListener('change', update);
        computer.removeEventListener('change', update);
    });

    return mode;
}

/**
 * Whether the window has the computer layout: the pages that change between phone and computer ask
 * this, so they change with the layout of the app (also with its height), never with a width of their own.
 *
 * @returns true on the computer layout
 */
export function useComputerLayout(): ComputedRef<boolean> {
    const mode = useLayoutMode();

    return computed((): boolean => mode.value === 'computer');
}

/**
 * Whether the window is a wide computer (from 1440 px), where a page shows one more column.
 *
 * @returns true on a computer at least 1440 px wide
 */
export function useWideComputerLayout(): ComputedRef<boolean> {
    const wide = useMediaQuery(WIDE_COMPUTER_QUERY);

    return computed((): boolean => wide.value);
}
