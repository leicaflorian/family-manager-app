import type { FormKitNode } from '@formkit/core';

/** The menus of FormKit Pro with a single choice. */
const MENUS = new Set(['dropdown', 'autocomplete']);

/**
 * A FormKit plugin: a menu closes after its empty option too («Nobody»,
 * «All»). FormKit Pro closes it only when the chosen option ends up selected,
 * and for it an empty value means that nothing is.
 *
 * @param node - a field that FormKit is creating
 */
export function closeOnEmptyChoice(node: FormKitNode): void {
    if (!MENUS.has(String(node.props.type)) || node.props.multiple) {
        return;
    }

    node.on('input', ({ payload }: { payload: unknown }) => {
        if ((payload === '' || payload === null) && node.props.expanded) {
            node.props.expanded = false;
        }
    });
}
