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

/**
 * A FormKit plugin: a menu with its empty option chosen («All the accounts»,
 * «None») shows that option. FormKit Pro reads an empty value as nothing
 * chosen and shows the placeholder, so the placeholder becomes its label, in
 * the colour of a choice and not of a hint.
 *
 * @param node - a field that FormKit is creating
 */
export function showEmptyChoice(node: FormKitNode): void {
    if (!MENUS.has(String(node.props.type)) || node.props.multiple) {
        return;
    }

    // A placeholder of the page wins: it is not the empty option
    const ownPlaceholder = node.props.placeholder !== undefined;

    const update = (): void => {
        const options: unknown = node.props.options;
        const empty = Array.isArray(options)
            ? options.find(
                  (option): option is { label: string } =>
                      typeof option === 'object' &&
                      option !== null &&
                      option.value === '' &&
                      typeof option.label === 'string',
              )
            : undefined;

        if (!ownPlaceholder && empty !== undefined) {
            node.props.placeholder = empty.label;
            node.props.placeholderClass = '$reset';
        }
    };

    update();
    node.on('prop:options', update);
}
