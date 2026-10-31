/**
 * The examples of the component catalogue: each component of the canvas has
 * next to it a `<Name>.examples.ts` with its cases, written with
 * `defineExamples`. The catalogue writes them as code in `CATALOG.md`, and
 * the page of the catalogue mounts them.
 */
import type { Component, VNodeChild } from 'vue';
import type { ComponentProps } from 'vue-component-type-helpers';

/** One case of a component: its props and, when needed, its slots. */
export type Example<C extends Component> = {
    /** The name of the case, in English and not translated, as `Overdue`. */
    name: string;
    /** The props of the case: the required ones of the component stay required. */
    props: ComponentProps<C>;
    /** The content of the slots, by name: a text, or a function that draws it. */
    slots?: Record<string, string | (() => VNodeChild)>;
};

/** The cases of one component, as an `.examples.ts` exports them. */
export type ComponentExamples = {
    component: Component;
    examples: Example<Component>[];
};

/**
 * The cases of a component, with the props checked against the component.
 *
 * @param component - The component.
 * @param examples - Its cases.
 * @returns The component with its cases, for the default export of the file.
 */
export function defineExamples<C extends Component>(
    component: C,
    examples: Example<C>[],
): ComponentExamples {
    // The props were checked against C above: the catalogue reads them as plain values
    return { component, examples: examples as Example<Component>[] };
}

/**
 * Every `.examples.ts` of `components/fm/`, by the name of its component.
 *
 * @returns The cases of each component that has them, as `{ FmRow: … }`.
 */
export function loadExamples(): Record<string, ComponentExamples> {
    const files = import.meta.glob<ComponentExamples>(
        '../components/fm/*.examples.ts',
        { eager: true, import: 'default' },
    );

    return Object.fromEntries(
        Object.entries(files).map(([path, examples]) => [
            path.replace(/^.*\/(\w+)\.examples\.ts$/, '$1'),
            examples,
        ]),
    );
}
