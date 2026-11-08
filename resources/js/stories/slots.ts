/**
 * Stories of a component that fills its slots: `withSlots` is the `render` of
 * such a story.
 */
import type { Component, ConcreteComponent, VNodeChild } from 'vue';
import { defineComponent, h } from 'vue';

/** The content of the slots, by name: a text, or a function that draws it. */
export type StorySlots = Record<string, string | (() => VNodeChild)>;

/**
 * The `render` of a story with slots: draws the component of the story with
 * the args of the story as props and the given content in its slots.
 *
 * @param slots - The content of each slot.
 * @returns The `render` to give to the story, as `render: withSlots({ default: 'Save' })`.
 */
export function withSlots(
    slots: StorySlots,
): (args: object, context: { component?: unknown }) => ConcreteComponent {
    return (args, { component }) =>
        defineComponent({
            // The args are reactive: reading them in the render follows the controls
            setup: () => () =>
                h(
                    component as Component,
                    { ...args },
                    Object.fromEntries(
                        Object.entries(slots).map(([name, content]) => [
                            name,
                            typeof content === 'string'
                                ? (): string => content
                                : content,
                        ]),
                    ),
                ),
        });
}
