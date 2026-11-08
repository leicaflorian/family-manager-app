import { render } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { defineComponent, h, ref } from 'vue';
import { useHeightTransition } from './useHeightTransition';

/** The callback of the last ResizeObserver made, which the test calls as the browser would. */
let resized: (() => void) | undefined;
/** The element the last ResizeObserver watches. */
let watched: Element | undefined;

/** A ResizeObserver that reports when the test says the content changed size. */
class FakeResizeObserver {
    /** @param callback - what the browser calls after a change of size */
    constructor(callback: () => void) {
        resized = callback;
    }

    /** @param element - the element to watch */
    observe(element: Element): void {
        watched = element;
    }

    /** Nothing to stop watching. */
    unobserve(): void {}

    /** Nothing to disconnect. */
    disconnect(): void {}
}

/** The height jsdom gives the box, which it does not measure. */
let height = 60;
/** The animations started on the box. */
let animations: Keyframe[][] = [];

/**
 * Mounts a box that animates its height, with its content inside.
 *
 * @returns the box
 */
function box(): HTMLElement {
    const Box = defineComponent({
        setup() {
            const outer = ref<HTMLElement>();
            const inner = ref<HTMLElement>();
            useHeightTransition(outer, inner);

            return () =>
                h('div', { ref: outer, 'data-testid': 'box' }, [
                    h('div', { ref: inner }, 'Create the income'),
                ]);
        },
    });
    const view = render(Box);
    const element = view.getByTestId('box');
    element.getBoundingClientRect = (): DOMRect =>
        DOMRect.fromRect({ height, width: 300 });
    element.getAnimations = (): Animation[] => [];
    element.animate = (keyframes): Animation => {
        animations.push(keyframes as Keyframe[]);

        return { cancel: () => {} } as Animation;
    };

    return element;
}

/**
 * Says whether the person asked for less motion.
 *
 * @param reduce - true for less motion
 */
function lessMotion(reduce: boolean): void {
    window.matchMedia = (query: string): MediaQueryList =>
        ({ matches: reduce && query.includes('reduce') }) as MediaQueryList;
}

const original = {
    observer: globalThis.ResizeObserver,
    media: window.matchMedia,
};

beforeEach(() => {
    globalThis.ResizeObserver =
        FakeResizeObserver as unknown as typeof ResizeObserver;
    lessMotion(false);
    height = 60;
    animations = [];
    resized = undefined;
    watched = undefined;
});

afterEach(() => {
    globalThis.ResizeObserver = original.observer;
    window.matchMedia = original.media;
});

describe('useHeightTransition', () => {
    it('watches the content, not the box it animates', () => {
        const element = box();

        expect(watched).toBe(element.firstElementChild);
    });

    it('takes the box from its old height to the new one when the content changes size', () => {
        box();
        resized?.();

        height = 140;
        resized?.();

        expect(animations).toHaveLength(1);
        expect(animations[0]?.[0]).toMatchObject({ height: '60px' });
        expect(animations[0]?.[1]).toMatchObject({ height: '140px' });
    });

    it('does not animate when the height stays the same', () => {
        box();
        resized?.();
        resized?.();

        expect(animations).toHaveLength(0);
    });

    it('does not animate for whoever asked for less motion', () => {
        lessMotion(true);
        box();
        resized?.();

        height = 140;
        resized?.();

        expect(animations).toHaveLength(0);
    });

    it('starts from where a running animation got to, so a quick second change does not jump', () => {
        const element = box();
        resized?.();
        const cancel = vi.fn();
        // Halfway through an animation from 60 to 140, the box is 100 px high
        height = 100;
        element.getAnimations = (): Animation[] => {
            const running = [
                { id: 'height-transition', cancel } as unknown as Animation,
            ];
            // Once cancelled, the box has its own height again
            cancel.mockImplementation(() => {
                height = 30;
            });

            return running;
        };

        resized?.();

        expect(cancel).toHaveBeenCalled();
        expect(animations[0]?.[0]).toMatchObject({ height: '100px' });
        expect(animations[0]?.[1]).toMatchObject({ height: '30px' });
    });

    it('leaves the transitions of the colours alone, and still animates the height', () => {
        const element = box();
        resized?.();
        const cancel = vi.fn();
        // The border and the background of a ticked box change with a CSS transition
        element.getAnimations = (): Animation[] => [
            { id: '', cancel } as unknown as Animation,
        ];

        height = 140;
        resized?.();

        expect(cancel).not.toHaveBeenCalled();
        expect(animations[0]?.[0]).toMatchObject({ height: '60px' });
        expect(animations[0]?.[1]).toMatchObject({ height: '140px' });
    });
});
