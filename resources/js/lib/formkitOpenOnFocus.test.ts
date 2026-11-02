import { FormKit, plugin as formkit } from '@formkit/vue';
import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import { h } from 'vue';
import formkitConfig from '@/formkit.config';
import { settle } from '@/test/page';

const people = [
    { value: '1', label: 'Marco' },
    { value: '2', label: 'Laura' },
];

/**
 * Mounts a menu with the configuration of the app.
 *
 * @param type - the type of the menu
 */
function menu(type: string): void {
    render(
        {
            render: () =>
                h(FormKit, {
                    type,
                    label: 'Person',
                    options: people,
                }),
        },
        { global: { plugins: [[formkit, formkitConfig]] } },
    );
}

describe('openOnFocus', () => {
    // An open list adds its listeners to the page 10 ms later: the test waits
    // for them, or they arrive when the page of the tests is already gone
    afterEach(() => settle());

    it.each(['autocomplete', 'taglist'])(
        'opens the list of a %s as soon as the person enters it, before typing',
        async (type) => {
            menu(type);

            await userEvent.tab();

            const box = await screen.findByRole('combobox', {
                name: 'Person',
            });
            expect(box).toHaveFocus();
            expect(box).toHaveAttribute('aria-expanded', 'true');
        },
    );

    it('still opens the list with a click', async () => {
        menu('taglist');

        await userEvent.click(
            await screen.findByRole('combobox', { name: 'Person' }),
        );

        expect(
            screen.getByRole('combobox', { name: 'Person' }),
        ).toHaveAttribute('aria-expanded', 'true');
    });
});
