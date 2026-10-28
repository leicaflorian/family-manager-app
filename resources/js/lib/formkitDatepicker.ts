import type { FormKitExtendableSchemaRoot, FormKitNode } from '@formkit/core';

/**
 * A FormKit plugin: the date in a datepicker changes only on a click (or with
 * the keyboard), not when the mouse goes over a day. FormKit Pro moves its
 * active date under the mouse and, while the calendar is open, writes that
 * date in the field, so the field changed at every day the mouse crossed. The
 * day under the mouse is still lit by its `hover:` class.
 *
 * @param node - a field that FormKit is creating
 */
export function chooseDateOnClick(node: FormKitNode): void {
    if (node.props.type !== 'datepicker') {
        return;
    }

    node.on('created', () => {
        if (node.context !== undefined) {
            node.context.handlers.mouseEnter = (): (() => void) => () => {};
        }
    });
}

/**
 * A FormKit plugin: the calendar of a datepicker marks today, besides the
 * chosen day, with `data-today` on its cell (FormKit Pro does not mark it).
 * The classes of `formkit.config.ts` draw it.
 *
 * @param node - a field that FormKit is creating
 */
export function markToday(node: FormKitNode): void {
    const definition = node.props.definition;
    const schema = definition?.schema;

    if (
        definition === undefined ||
        typeof schema !== 'function' ||
        node.props.type !== 'datepicker'
    ) {
        return;
    }

    // As keepDropdownList in formkit.config.ts: the attribute is added to the
    // sections the page gives to the field
    const withToday: FormKitExtendableSchemaRoot = (sections) =>
        schema({
            ...sections,
            dayCell: {
                attrs: { 'data-today': '$fns.isToday($day) || undefined' },
            },
        });
    node.props.definition = { ...definition, schema: withToday };

    node.on('created', () => {
        if (node.context !== undefined) {
            node.context.fns.isToday = (day: unknown): boolean =>
                day instanceof Date &&
                day.toDateString() === new Date().toDateString();
        }
    });
}
