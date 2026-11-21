/**
 * Back of an inner page, shared by the top bar of the computer, the row above
 * the title of the tablet and the link above the title of the phone: which page
 * is above in the trail, whether Back is shown, and what a click does.
 */
import { router } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';
import type { TrailPiece } from '@/lib/pageTrail';
import { canGoBack } from '@/lib/visitHistory';

/** What `usePageBack` gives to the component. */
export type PageBack = {
    /** The nearest piece before the last that has a link, where Back leads without a history. */
    parent: ComputedRef<TrailPiece | undefined>;
    /** Whether Back is shown. Reactive: it reads the depth of the history. */
    visible: () => boolean;
    /** The click on Back. */
    back: (event: MouseEvent) => void;
};

/**
 * Builds Back for the page with the given trail.
 *
 * @param trail - returns the pieces of the trail; the last one is the open page
 * @param section - returns true when the open page is the page of a section: no Back
 * @returns the parent piece, whether Back is visible, and the click handler
 */
export function usePageBack(
    trail: () => TrailPiece[],
    section: () => boolean,
): PageBack {
    const parent = computed((): TrailPiece | undefined =>
        trail()
            .slice(0, -1)
            .reverse()
            .find((piece) => piece.href !== undefined),
    );

    /**
     * Whether Back is shown: on an inner page, when it can go back in the
     * history or up in the trail. `canGoBack()` reads a reactive depth, so a
     * template that calls this renders again after each visit.
     *
     * @returns true when Back is shown
     */
    function visible(): boolean {
        return !section() && (canGoBack() || parent.value !== undefined);
    }

    /**
     * Back: the previous page of the history when it is a page of the app,
     * otherwise the page above in the trail. With a modifier key on the link
     * the browser opens it as it wants (a new tab, a new window); the button
     * without a page above always goes back.
     *
     * @param event - the click on Back
     */
    function back(event: MouseEvent): void {
        const modified =
            event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;
        const href = parent.value?.href;

        if (modified && href !== undefined) {
            return;
        }

        event.preventDefault();

        if (canGoBack()) {
            window.history.back();
        } else if (href !== undefined) {
            router.visit(href);
        }
    }

    return { parent, visible, back };
}
