/**
 * Helper for the tests of the pages: mounts them as the app does, with FormKit.
 * The Inertia `<Head>` and the translations are set up by `setup.ts` for all
 * the tests.
 */
import { plugin as formkit } from '@formkit/vue';
import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
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
 * Opens a menu, as the person does. With the same menu twice on the page (the
 * filters of the computer and of the phone) it opens the first one.
 *
 * @param label - the label of the menu
 * @returns the list of the options
 */
export async function openMenu(label: string): Promise<HTMLElement> {
    // A dropdown opens with its button, a menu with search with its text box
    const [menu] = [
        ...screen.queryAllByRole('button', { name: label }),
        ...screen.queryAllByRole('combobox', { name: label }),
    ];
    await userEvent.click(menu!);

    // The closed menus keep their list in the page: the one wanted is in this field
    return within(menu!.closest<HTMLElement>('[data-type]')!).findByRole(
        'listbox',
    );
}

/**
 * Chooses an option in a menu, as the person does: opens it, taps the option
 * and waits for FormKit.
 *
 * @param label - the label of the menu
 * @param option - the text of the option
 */
export async function choose(label: string, option: string): Promise<void> {
    const list = await openMenu(label);
    await userEvent.click(within(list).getByRole('option', { name: option }));
    await settle();
}

/**
 * The options of an open list as the person sees them: without the texts
 * only for screen readers.
 *
 * @param list - the list of a menu
 * @returns the text of each option, in order
 */
export function shown(list: HTMLElement): string[] {
    return within(list)
        .getAllByRole('option')
        .map((option) => {
            const copy = option.cloneNode(true) as HTMLElement;
            copy.querySelectorAll('.sr-only').forEach((hidden) =>
                hidden.remove(),
            );

            return copy.textContent?.trim() ?? '';
        });
}

/**
 * Searches in a menu with search, as the person does: opens it and types.
 *
 * @param label - the label of the menu
 * @param text - what the person types
 * @returns the list of the options found
 */
export async function search(
    label: string,
    text: string,
): Promise<HTMLElement> {
    const list = await openMenu(label);
    await userEvent.type(
        screen.getAllByRole('combobox', { name: label })[0]!,
        text,
    );
    await settle();
    await new Promise((resolve) => setTimeout(resolve, 250));

    return list;
}

/**
 * Presses a key on the page, as the person does.
 *
 * @param init - the key and its modifiers
 * @returns the event, to see whether the browser was stopped
 */
export function press(init: KeyboardEventInit): KeyboardEvent {
    const event = new KeyboardEvent('keydown', {
        bubbles: true,
        cancelable: true,
        ...init,
    });
    document.body.dispatchEvent(event);

    return event;
}

/**
 * Presses ⌘S, which saves the form of the page.
 */
export function pressSave(): void {
    press({ key: 's', metaKey: true });
}
