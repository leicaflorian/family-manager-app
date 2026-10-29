import { FormKit, plugin as formkit } from '@formkit/vue';
import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { h } from 'vue';
import formkitConfig from '@/formkit.config';

const accounts = [
    { value: '', label: 'All the accounts' },
    { value: '1', label: 'BPER account' },
];

/**
 * Mounts a menu with the configuration of the app.
 *
 * @param type - the type of the menu
 * @param value - the value chosen
 */
function menu(type: string, value: string): void {
    render(
        {
            render: () =>
                h(FormKit, {
                    type,
                    label: 'Account',
                    options: accounts,
                    modelValue: value,
                }),
        },
        { global: { plugins: [[formkit, formkitConfig]] } },
    );
}

describe('showEmptyChoice', () => {
    it('shows the empty option of a dropdown when it is the chosen one', async () => {
        menu('dropdown', '');

        expect(
            await screen.findByRole('button', { name: /^Account/ }),
        ).toHaveTextContent('All the accounts');
    });

    it('shows the empty option as a choice, not as a grey hint', async () => {
        menu('dropdown', '');

        const box = await screen.findByRole('button', { name: /^Account/ });

        expect(
            box.querySelector('[data-is-placeholder]')?.className ?? '',
        ).not.toContain('text-ink-soft');
    });

    it('shows the chosen option and not the empty one', async () => {
        menu('dropdown', '1');

        const box = await screen.findByRole('button', { name: /^Account/ });

        expect(box).toHaveTextContent('BPER account');
        expect(box).not.toHaveTextContent('All the accounts');
    });
});
