import type { FormKitNode } from '@formkit/core';
import { trans } from 'laravel-vue-i18n';

/** The menus of FormKit Pro that are searched by typing. */
const SEARCHED = new Set(['autocomplete', 'taglist']);

/**
 * A FormKit plugin: a menu searched by typing says «No results» when nothing
 * matches what the person wrote. FormKit Pro shows an empty list, which looks
 * like a broken field. A page can give its own message with `empty-message`.
 *
 * @param node - a field that FormKit is creating
 */
export function sayNoResults(node: FormKitNode): void {
    if (!SEARCHED.has(String(node.props.type))) {
        return;
    }

    node.props.showEmptyMessage ??= true;
    node.props.emptyMessage ??= trans('No results');
}
