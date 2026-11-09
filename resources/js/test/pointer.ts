/**
 * Pointer events for the tests. jsdom has no `PointerEvent`: a `MouseEvent` with the fields of a pointer
 * (`pointerId`, `pointerType`) is what the components read.
 */
import { fireEvent } from '@testing-library/vue';

/** Where and how a pointer event happens. */
export type PointerInit = {
    x?: number;
    y?: number;
    pointerId?: number;
    pointerType?: 'touch' | 'mouse' | 'pen';
    button?: number;
};

/**
 * Sends a pointer event to an element.
 *
 * @param target - the element
 * @param type - `pointerdown`, `pointermove`, `pointerup`, `pointercancel`, `pointerleave`…
 * @param init - position, pointer and kind (a touch, with id 1, by default)
 * @returns a promise that is done when Vue has drawn again
 */
export function firePointer(
    target: Element,
    type: string,
    init: PointerInit = {},
): Promise<void> {
    const event = new MouseEvent(type, {
        bubbles: true,
        cancelable: true,
        clientX: init.x ?? 0,
        clientY: init.y ?? 0,
        button: init.button ?? 0,
    });

    Object.defineProperty(event, 'pointerId', { value: init.pointerId ?? 1 });
    Object.defineProperty(event, 'pointerType', {
        value: init.pointerType ?? 'touch',
    });

    return fireEvent(target, event).then(() => undefined);
}

/**
 * Makes an element measure as a box, because jsdom measures nothing.
 *
 * @param element - the element
 * @param box - its left, top, width and height
 */
export function measure(
    element: Element,
    box: { left?: number; top?: number; width: number; height: number },
): void {
    const left = box.left ?? 0;
    const top = box.top ?? 0;

    element.getBoundingClientRect = () =>
        ({
            left,
            top,
            width: box.width,
            height: box.height,
            right: left + box.width,
            bottom: top + box.height,
            x: left,
            y: top,
            toJSON: () => ({}),
        }) as DOMRect;
}
