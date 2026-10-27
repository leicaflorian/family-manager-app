/**
 * Helper for the tests of the pages: mounts them as the app does, with FormKit.
 * The Inertia `<Head>` and the translations are set up by `setup.ts` for all
 * the tests.
 */
import { plugin as formkit } from '@formkit/vue';
import { fireEvent, render, screen } from '@testing-library/vue';
import type { RenderResult } from '@testing-library/vue';
import type { Component } from 'vue';
import formkitConfig from '@/formkit.config';

/**
 * Mounts a page for a test.
 *
 * @param page - the component of the page
 * @param props - the props the server would give it
 * @returns the result of Testing Library
 */
export function renderPage(
    page: Component,
    props: Record<string, unknown> = {},
): RenderResult {
    return render(page, {
        props,
        global: {
            plugins: [[formkit, formkitConfig]],
        },
    });
}

/**
 * Waits for FormKit to hand a typed or chosen value to the `v-model`: it does
 * so after a short delay (20 ms), not at the event.
 *
 * @returns a promise resolved once the value is in the model
 */
export function settle(): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, 30));
}

/**
 * Chooses a value in a menu, as the person does, and waits for FormKit.
 *
 * @param label - the label of the menu
 * @param value - the value of the option
 */
export async function choose(label: string, value: string): Promise<void> {
    const select = screen.getByLabelText(label) as HTMLSelectElement;
    select.value = value;
    await fireEvent.input(select);
    await settle();
}
