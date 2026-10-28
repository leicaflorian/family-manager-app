import type { FormKitNode } from '@formkit/core';

/**
 * A FormKit plugin: the toggles of FormKit Pro are a checkbox under a track,
 * and this tells screen readers that they are a switch (on/off), not a box to
 * tick.
 *
 * @param node - a field that FormKit is creating
 */
export function toggleAsSwitch(node: FormKitNode): void {
    if (node.props.type !== 'toggle') {
        return;
    }

    node.props.attrs = { role: 'switch', ...node.props.attrs };
}
