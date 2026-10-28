import { FormKit, plugin as formkit } from '@formkit/vue';
import { render, screen, waitFor, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { h } from 'vue';
import formkitConfig from '@/formkit.config';

describe('the FormKit Pro fields of the app', () => {
    it('writes the amounts in euros, the Italian way', async () => {
        render(
            {
                render: () =>
                    h(FormKit, {
                        type: 'currency',
                        label: 'Amount',
                        modelValue: '84.2',
                    }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        // Intl puts a non-breaking space before the €
        expect(await screen.findByLabelText('Amount')).toHaveValue(
            '84,20\u00a0€',
        );
    });

    it('draws the toggle as a switch beside its label, not inside the box of the fields', async () => {
        render(
            {
                render: () =>
                    h(FormKit, {
                        type: 'toggle',
                        label: 'Expiring within 60 days',
                    }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        const toggle = await screen.findByRole('switch', {
            name: 'Expiring within 60 days',
        });

        // The real checkbox stays, for the keyboard and the screen readers
        expect(toggle).toHaveClass('sr-only', 'peer');
        expect(toggle.closest('label')).not.toHaveClass('border-[1.5px]');
        expect(toggle.closest('label')?.parentElement).toHaveClass(
            'flex',
            'items-center',
        );
        // The track comes right after the checkbox
        expect(toggle.nextElementSibling).toHaveClass(
            'rounded-full',
            'peer-checked:bg-action',
        );
        expect(screen.getByText('Expiring within 60 days')).not.toHaveClass(
            'block',
        );
    });

    it('draws the tags as grey chips inside a box that grows, without the arrow of a menu', async () => {
        render(
            {
                render: () =>
                    h(FormKit, {
                        type: 'taglist',
                        label: 'Tags',
                        options: [],
                        allowNewValues: true,
                        modelValue: ['salary', '2026'],
                    }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        const input = await screen.findByRole('combobox', { name: 'Tags' });
        const field = input.closest<HTMLElement>('[data-type]')!;

        expect(within(field).getByText('salary').parentElement).toHaveClass(
            'rounded-full',
            'bg-divider',
        );
        expect(input.parentElement).toHaveClass('flex-wrap');
        expect(within(field).getByRole('button', { name: 'Open' })).toHaveClass(
            'hidden',
        );
    });

    it('leaves the fields under a closed dropdown clickable', async () => {
        render(
            {
                render: () =>
                    h(FormKit, {
                        type: 'dropdown',
                        label: 'Status',
                        options: ['Active', 'Archived'],
                    }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        const list = await screen.findByRole('listbox', { hidden: true });

        // The closed list stays in the page for its fade: what holds it must
        // not catch the clicks meant for the fields below
        expect(list.parentElement).toHaveClass('pointer-events-none');
        // …nor make the page longer: closed, it is 0 high (after the fade)
        expect(list.parentElement).toHaveClass(
            'max-h-0!',
            'overflow-hidden!',
            'delay-150',
            'in-data-expanded:max-h-[400px]!',
        );
        expect(list).toHaveClass('in-data-expanded:pointer-events-auto');
    });

    it('opens the calendar of the datepicker from its button', async () => {
        render(
            { render: () => h(FormKit, { type: 'datepicker', label: 'Date' }) },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        const button = await screen.findByRole('button', {
            name: 'Choose date',
        });
        expect(button.querySelector('svg')).not.toBeNull();
        await userEvent.click(button);

        expect(await screen.findByRole('dialog')).toBeInTheDocument();
    });

    it('opens the calendar over the page, with the chosen day in blue', async () => {
        render(
            {
                render: () =>
                    h(FormKit, {
                        type: 'datepicker',
                        label: 'Date',
                        modelValue: '2026-10-15',
                    }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        await userEvent.click(
            await screen.findByRole('button', { name: /Change date/ }),
        );
        const calendar = await screen.findByRole('dialog');

        expect(calendar).toHaveClass('absolute', 'bg-white', 'shadow-menu');
        expect(
            within(calendar).getByRole('gridcell', { selected: true })
                .firstElementChild,
        ).toHaveClass('in-aria-selected:bg-action!');
    });

    it('changes the date in the field only on a click, not when the mouse goes over a day', async () => {
        render(
            {
                render: () =>
                    h(FormKit, {
                        type: 'datepicker',
                        label: 'Date',
                        modelValue: '2026-10-15',
                    }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        await userEvent.click(
            await screen.findByRole('button', { name: /Change date/ }),
        );
        const calendar = await screen.findByRole('dialog');
        const field = screen.getByLabelText('Date');
        const day = within(calendar).getByRole('gridcell', {
            name: /20 October 2026|October 20, 2026/,
        });

        await userEvent.hover(day);
        expect(field).toHaveValue('15/10/2026');

        await userEvent.click(day);
        expect(field).toHaveValue('20/10/2026');
    });

    it('hides the format in an empty date until the person types in it', async () => {
        render(
            { render: () => h(FormKit, { type: 'datepicker', label: 'Date' }) },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        // FormKit Pro writes DD/MM/YYYY in the field as text, to type the date
        // over it: empty and without the focus, the text is transparent
        const field = await screen.findByLabelText('Date');

        expect(field.closest('[data-empty]')).not.toBeNull();
        expect(field).toHaveClass('in-data-empty:not-focus:text-transparent');
    });

    it('starts the weeks of the calendar on Monday', async () => {
        render(
            { render: () => h(FormKit, { type: 'datepicker', label: 'Date' }) },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        await userEvent.click(
            await screen.findByRole('button', { name: 'Choose date' }),
        );
        const calendar = await screen.findByRole('dialog');

        expect(
            within(calendar).getAllByRole('columnheader')[0],
        ).toHaveTextContent(/^m/i);
    });

    it('keeps the chosen day blue under the mouse, and fades the colours of the days', async () => {
        render(
            {
                render: () =>
                    h(FormKit, {
                        type: 'datepicker',
                        label: 'Date',
                        modelValue: '2026-10-15',
                    }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        await userEvent.click(
            await screen.findByRole('button', { name: /Change date/ }),
        );
        const chosen = within(await screen.findByRole('dialog')).getByRole(
            'gridcell',
            { selected: true },
        ).firstElementChild;

        expect(chosen).toHaveClass(
            'in-aria-selected:bg-action!',
            'motion-safe:transition-colors',
        );
    });

    it('marks today in the calendar, besides the chosen day', async () => {
        const now = new Date();
        const today = [
            now.getFullYear(),
            String(now.getMonth() + 1).padStart(2, '0'),
            String(now.getDate()).padStart(2, '0'),
        ].join('-');
        render(
            {
                render: () =>
                    h(FormKit, {
                        type: 'datepicker',
                        label: 'Date',
                        modelValue: today,
                    }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        await userEvent.click(
            await screen.findByRole('button', { name: /Change date/ }),
        );
        const calendar = await screen.findByRole('dialog');
        const marked = calendar.querySelectorAll('[data-today]');

        expect(marked).toHaveLength(1);
        expect(marked[0]).toHaveAttribute('aria-selected', 'true');
        // Chosen, today is white on blue as any chosen day: the blue text of
        // today would not show
        expect(marked[0]?.firstElementChild).toHaveClass(
            'in-data-today:ring-1',
            'in-aria-selected:text-white!',
        );
    });

    it('fades the calendar in as the other menus', async () => {
        render(
            { render: () => h(FormKit, { type: 'datepicker', label: 'Date' }) },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        await userEvent.click(
            await screen.findByRole('button', { name: 'Choose date' }),
        );

        expect(await screen.findByRole('dialog')).toHaveClass(
            'motion-safe:transition-[opacity,translate]',
            'starting:opacity-0',
            'starting:-translate-y-1',
        );
    });

    it.each([
        { fieldTop: 700, side: 'top' },
        { fieldTop: 100, side: 'bottom' },
    ])(
        'opens the list of a dropdown on the $side when the field is $fieldTop px from the top of an 800 px window',
        async ({ fieldTop, side }) => {
            window.innerHeight = 800;
            render(
                {
                    render: () =>
                        h(FormKit, {
                            type: 'dropdown',
                            label: 'Link it to',
                            options: ['Home', 'Car'],
                        }),
                },
                { global: { plugins: [[formkit, formkitConfig]] } },
            );

            const menu = await screen.findByRole('button', {
                name: 'Link it to',
            });
            const list = document.getElementById(`${menu.id}_listbox`);
            // jsdom has no layout: the field and its list get the sizes of a page
            menu.getBoundingClientRect = (): DOMRect =>
                DOMRect.fromRect({ y: fieldTop, height: 46, width: 300 });
            list!.getBoundingClientRect = (): DOMRect =>
                DOMRect.fromRect({ height: 288, width: 300 });
            await userEvent.click(menu);

            await waitFor(() =>
                expect(
                    document.getElementById(`${menu.id}_popover`),
                ).toHaveAttribute('data-side', side),
            );
            expect(document.getElementById(`${menu.id}_popover`)).toHaveClass(
                'data-[side=top]:top-auto!',
                'data-[side=top]:bottom-full!',
                'data-[side=top]:mb-1.5',
            );
        },
    );

    it('closes the dropdown after choosing the empty option, as after any other', async () => {
        render(
            {
                render: () =>
                    h(FormKit, {
                        type: 'dropdown',
                        label: 'Whose it is',
                        options: [
                            { label: 'Nobody', value: '' },
                            { label: 'Laura', value: '7' },
                        ],
                        modelValue: '7',
                    }),
            },
            { global: { plugins: [[formkit, formkitConfig]] } },
        );

        const menu = await screen.findByRole('button', { name: 'Whose it is' });
        await userEvent.click(menu);
        await userEvent.click(
            await screen.findByRole('option', { name: 'Nobody' }),
        );

        await waitFor(() =>
            expect(menu).toHaveAttribute('aria-expanded', 'false'),
        );
    });
});
