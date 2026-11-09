import type { FormKitNode } from '@formkit/core';

/** The menus of FormKit Pro with a single choice. */
const MENUS = new Set(['dropdown', 'autocomplete']);

/** A choice of a menu as FormKit Pro keeps it: `noOptionFound` when its option was not there when it was chosen. */
type Kept = { value?: unknown; label?: unknown; noOptionFound?: boolean };

/**
 * A FormKit plugin: a menu shows the label of its value also when the value and its option arrive together (the
 * scanners of the computer, the resolutions of the scanner chosen). FormKit Pro looks the value up among the
 * options it had before, keeps the value itself as the label (`noOptionFound`) and does not look again when the
 * options change; here, when they change, a choice without its option takes it from the new ones.
 *
 * @param node - a field that FormKit is creating
 */
export function labelLateOptions(node: FormKitNode): void {
    if (!MENUS.has(String(node.props.type)) || node.props.multiple) {
        return;
    }

    node.on('prop:options', () => {
        const selections = (node.props.selections ?? []) as Kept[];

        if (!selections.some((one) => one.noOptionFound)) {
            return;
        }

        const options: unknown = node.props.options;
        const found = Array.isArray(options) ? (options as Kept[]) : [];
        const labelled = (list: Kept[]): Kept[] =>
            list.map((one) =>
                one.noOptionFound
                    ? (found.find((option) => option.value === one.value) ??
                      one)
                    : one,
            );

        node.props.memoOptions = labelled(
            (node.props.memoOptions ?? []) as Kept[],
        );
        node.props.selections = labelled(selections);
    });
}
