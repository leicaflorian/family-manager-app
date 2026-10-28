import type { FormKitNode } from '@formkit/core';

/**
 * A FormKit plugin: in a taglist, Tab adds the highlighted suggestion, as a
 * click on it, while Enter keeps adding what was written. FormKit Pro has no
 * key for the suggestion: Tab would only leave the field.
 *
 * @param node - a field that FormKit is creating
 */
export function tabChoosesSuggestion(node: FormKitNode): void {
    if (node.props.type !== 'taglist') {
        return;
    }

    node.on('mounted', () => {
        // In the capture phase: FormKit Pro closes the list on Tab first
        document.getElementById(String(node.props.id))?.addEventListener(
            'keydown',
            (event) => {
                const highlighted = suggestion(node);

                if (event.key === 'Tab' && highlighted !== null) {
                    event.preventDefault();
                    highlighted.click();
                }
            },
            true,
        );
    });
}

/**
 * The suggestion highlighted in the open list of a taglist.
 *
 * @param node - the taglist
 * @returns its entry in the list, or `null` if the list is closed or nothing is highlighted
 */
function suggestion(node: FormKitNode): HTMLElement | null {
    const active = node.props.activeDescendant;

    if (!node.props.expanded || typeof active !== 'string') {
        return null;
    }

    return document.getElementById(active);
}
