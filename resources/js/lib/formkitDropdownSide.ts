import type { FormKitNode } from '@formkit/core';

/** The fields of FormKit Pro with a list that opens under them. */
export const DROPDOWNS = new Set(['dropdown', 'autocomplete', 'taglist']);

/** The space between the field and its list, in pixels (`mt-1.5`). */
const GAP = 6;

/**
 * A FormKit plugin: the list of a dropdown opens over the field when under it
 * there is no room for the list and over it there is. It writes the side in
 * `data-side` (`top` or `bottom`) of the holder of the list, and the classes
 * of `formkit.config.ts` move it. FormKit Pro would turn the list by itself,
 * but it measures the holder, which is 0 high when the list opens (the closed
 * list stays in the page, in a holder with no height).
 *
 * @param node - a field that FormKit is creating
 */
export function openOnTheSideWithRoom(node: FormKitNode): void {
    if (!DROPDOWNS.has(String(node.props.type))) {
        return;
    }

    node.on('prop:expanded', ({ payload }: { payload: unknown }) => {
        if (payload !== true) {
            return;
        }

        const id = String(node.props.id);
        const field = document.getElementById(id);
        const list = document.getElementById(`${id}_listbox`);
        const holder = document.getElementById(`${id}_popover`);

        if (field === null || list === null || holder === null) {
            return;
        }

        holder.dataset.side = side(
            field.getBoundingClientRect(),
            list.getBoundingClientRect().height,
        );
    });
}

/**
 * The side where a list fits: under the field, unless there is no room under
 * it and there is over it.
 *
 * @param field - where the field is in the window
 * @param listHeight - how high the list is
 * @returns `top` or `bottom`
 */
function side(field: DOMRect, listHeight: number): 'top' | 'bottom' {
    const fitsUnder = field.bottom + GAP + listHeight <= window.innerHeight;
    const fitsOver = field.top - GAP - listHeight >= 0;

    return !fitsUnder && fitsOver ? 'top' : 'bottom';
}
