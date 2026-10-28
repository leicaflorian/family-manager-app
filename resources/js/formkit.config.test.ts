import { FormKit, plugin as formkit } from '@formkit/vue';
import { render, screen, waitFor } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { h, ref } from 'vue';
import formkitConfig from '@/formkit.config';

describe('formkit.config', () => {
    it('has the FormKit Pro fields, such as the toggle', async () => {
        render(
            {
                render: () =>
                    h(FormKit, { type: 'toggle', label: 'Notify me' }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        expect(
            await screen.findByRole('switch', { name: 'Notify me' }),
        ).toBeInTheDocument();
    });

    it('chooses an option from the dropdown', async () => {
        const chosen = ref<unknown>('');
        render(
            {
                render: () =>
                    h(FormKit, {
                        type: 'dropdown',
                        label: 'Whose it is',
                        options: [
                            { label: 'Nobody', value: '' },
                            { label: 'Marco', value: '1' },
                            { label: 'Giulia', value: '2' },
                        ],
                        modelValue: chosen.value,
                        'onUpdate:modelValue': (value: unknown) => {
                            chosen.value = value;
                        },
                    }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        await userEvent.click(
            await screen.findByRole('button', { name: 'Whose it is' }),
        );
        await userEvent.click(
            await screen.findByRole('option', { name: 'Giulia' }),
        );

        // FormKit commits the value after a short delay
        await waitFor(() => expect(chosen.value).toBe('2'));
    });

    it('draws its own arrow, without downloading icons', async () => {
        const { container } = render(
            {
                render: () =>
                    h(FormKit, {
                        type: 'dropdown',
                        label: 'Status',
                        options: ['Valid'],
                    }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        await screen.findByRole('button', { name: 'Status' });
        await waitFor(() => {
            expect(
                container.querySelector('.formkit-select-icon svg'),
            ).not.toBeNull();
        });
    });

    it('opens the list of the dropdown with a short fade, as a menu over the page', async () => {
        render(
            {
                render: () =>
                    h(FormKit, {
                        type: 'dropdown',
                        label: 'Whose it is',
                        options: ['Marco', 'Giulia'],
                    }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        await userEvent.click(
            await screen.findByRole('button', { name: 'Whose it is' }),
        );
        const list = await screen.findByRole('listbox');

        expect(list).toHaveClass(
            'in-data-expanded:opacity-100',
            'bg-white',
            'shadow-menu',
        );
        expect(list.parentElement).toHaveClass('absolute');
    });

    it('draws the checkbox as the canvas: the box and the text centred, the text at 16px', async () => {
        render(
            {
                render: () =>
                    h(FormKit, { type: 'checkbox', label: 'Stay signed in' }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        const box = await screen.findByRole('checkbox', {
            name: 'Stay signed in',
        });

        expect(box.closest('label')).toHaveClass('items-center', 'gap-3');
        expect(screen.getByText('Stay signed in')).toHaveClass('text-base');
    });

    it('draws its own box for the checkbox, with the border and corners of the fields', async () => {
        const { container } = render(
            {
                render: () =>
                    h(FormKit, { type: 'checkbox', label: 'Expiring soon' }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        const box = await screen.findByRole('checkbox', {
            name: 'Expiring soon',
        });

        expect(box).toHaveClass('appearance-none', 'border-edge', 'rounded-md');
        await waitFor(() => {
            expect(container.querySelector('label svg')).not.toBeNull();
        });
    });

    it('keeps the list of the dropdown while it fades out on closing', async () => {
        const { container } = render(
            {
                render: () =>
                    h(FormKit, {
                        type: 'dropdown',
                        label: 'Whose it is',
                        options: ['Marco', 'Giulia'],
                    }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        const button = await screen.findByRole('button', {
            name: 'Whose it is',
        });
        await userEvent.click(button);
        await screen.findByRole('listbox');
        await userEvent.keyboard('{Escape}');

        // Still in the page, hidden by its classes once the field is no longer expanded
        await waitFor(() =>
            expect(container.querySelector('[data-expanded]')).toBeNull(),
        );
        expect(container.querySelector('ul.formkit-listbox')).toHaveClass(
            'invisible',
            'opacity-0',
        );
    });

    it('shows the toggle buttons as segments: a grey track, the chosen one white', async () => {
        const chosen = ref<unknown>('active');
        render(
            {
                render: () =>
                    h(FormKit, {
                        type: 'togglebuttons',
                        label: 'Status',
                        enforced: true,
                        options: [
                            { label: 'Active', value: 'active' },
                            { label: 'Archived', value: 'archived' },
                        ],
                        modelValue: chosen.value,
                        'onUpdate:modelValue': (value: unknown) => {
                            chosen.value = value;
                        },
                    }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        const active = await screen.findByRole('button', { name: 'Active' });
        expect(active).toHaveAttribute('aria-pressed', 'true');
        expect(active).toHaveClass('aria-pressed:bg-white', 'h-[38px]');
        expect(active.closest('ul')).toHaveClass('flex', 'bg-segment', 'p-1');
        expect(active.closest('li')).toHaveClass('flex-1');

        await userEvent.click(screen.getByRole('button', { name: 'Archived' }));
        await waitFor(() => expect(chosen.value).toBe('archived'));
    });
});
