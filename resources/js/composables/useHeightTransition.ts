import type { Ref } from 'vue';
import { onBeforeUnmount, onMounted } from 'vue';

/** How long the change of height lasts, in milliseconds. */
const DURATION = 200;

/** The curve of the change: quick at the start, soft at the end. */
const EASING = 'cubic-bezier(0.2, 0, 0, 1)';

/**
 * The name of the animations of the height: `getAnimations()` gives the CSS
 * transitions of the box too (its colours), which are left alone.
 */
const ID = 'height-transition';

/**
 * Whether the person asked the system for less motion.
 *
 * @returns true when the animations are to be left out
 */
function wantsLessMotion(): boolean {
    return (
        typeof window.matchMedia === 'function' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

/**
 * Animates the height of a box when its content changes size, for whatever
 * reason: fields that open under a tick, a text that changes, a warning that
 * comes. A box cannot go to `height: auto` with CSS alone, so the content is
 * watched with a `ResizeObserver` and the box goes from its old height to the
 * new one with the Web Animations API, which leaves no style behind. The
 * content is watched, not the box, because the animation changes the size of
 * the box. A second change during the animation starts from where the first
 * got to. Nothing moves for whoever asked for less motion.
 *
 * @param box - the element whose height changes, with its padding and border
 * @param content - the element inside it that holds what changes
 */
export function useHeightTransition(
    box: Ref<HTMLElement | undefined>,
    content: Ref<HTMLElement | undefined>,
): void {
    /** The height of the box after the last change, null before the first report. */
    let last: number | null = null;
    let observer: ResizeObserver | undefined;

    /** Takes the box from its height of now to the one its content gives it. */
    function follow(): void {
        const element = box.value;

        if (!element) {
            return;
        }

        const running = element
            .getAnimations()
            .filter((animation) => animation.id === ID);
        // While an animation runs the box is between two heights: the new one starts from there
        const from =
            running.length > 0 ? element.getBoundingClientRect().height : last;
        running.forEach((animation) => animation.cancel());
        const to = element.getBoundingClientRect().height;
        last = to;

        if (from === null || from === to || wantsLessMotion()) {
            return;
        }

        element.animate(
            [
                { height: `${from}px`, overflow: 'hidden' },
                { height: `${to}px`, overflow: 'hidden' },
            ],
            { id: ID, duration: DURATION, easing: EASING },
        );
    }

    onMounted(() => {
        if (!content.value) {
            return;
        }

        observer = new ResizeObserver(follow);
        observer.observe(content.value);
    });

    onBeforeUnmount(() => observer?.disconnect());
}
