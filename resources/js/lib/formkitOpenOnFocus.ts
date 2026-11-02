import type { FormKitNode } from '@formkit/core';

/** The menus of FormKit Pro that are searched by typing. */
const SEARCHED = new Set(['autocomplete', 'taglist']);

/**
 * A FormKit plugin: a menu searched by typing (category, person, the types of
 * deadline) shows its list as soon as the person enters it, with a click or
 * with Tab. FormKit Pro opens it only after the first letter, so whoever does
 * not know the choices sees an empty box. A page can still turn it off with
 * `:open-on-focus="false"`.
 *
 * @param node - a field that FormKit is creating
 */
export function openOnFocus(node: FormKitNode): void {
    if (!SEARCHED.has(String(node.props.type))) {
        return;
    }

    node.props.openOnFocus ??= true;
}
