import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { accountOptions } from '@/test/fixtures';
import { openMenu, renderPage, settle, shown } from '@/test/page';
import FmMovementFilters from './FmMovementFilters.vue';

const none: App.Data.MovementFiltersData = {
    search: null,
    kind: null,
    account: null,
    min: null,
    max: null,
    from: null,
    to: null,
};

const accounts = accountOptions.map(({ value, label }) => ({ value, label }));

describe('FmMovementFilters', () => {
    it('sends the filters with the kind chosen', async () => {
        const view = renderPage(FmMovementFilters, { filters: none, accounts });

        await userEvent.click(screen.getByRole('button', { name: 'Expenses' }));
        await settle();

        expect(view.emitted('change')?.at(-1)).toEqual([
            { ...none, kind: 'expense' },
        ]);
    });

    it('lists «All the accounts» first and sends the account chosen', async () => {
        const view = renderPage(FmMovementFilters, {
            filters: { ...none, search: 'spesa' },
            accounts,
        });

        const list = await openMenu('Bank account');
        expect(shown(list)).toEqual([
            'All the accounts',
            'Conto BPER',
            'Carta Laura',
        ]);
        await userEvent.click(
            within(list).getByRole('option', { name: 'Carta Laura' }),
        );
        await settle();

        // the search typed above the list is kept
        expect(view.emitted('change')?.at(-1)).toEqual([
            { ...none, search: 'spesa', account: 2 },
        ]);
    });

    it('has the amounts and the days, and says what happens without days', () => {
        renderPage(FmMovementFilters, {
            filters: { ...none, from: '2026-09-01', to: '2026-10-31' },
            accounts,
        });

        expect(screen.getByLabelText('At least')).toBeInTheDocument();
        expect(screen.getByLabelText('At most')).toBeInTheDocument();
        expect(screen.getByLabelText('From')).toHaveValue('01/09/2026');
        expect(screen.getByLabelText('To')).toHaveValue('31/10/2026');
        expect(
            screen.getByText('Without dates I look in every month.'),
        ).toBeInTheDocument();
    });

    it('offers to remove the filters only when one is on, and removes them all', async () => {
        const empty = renderPage(FmMovementFilters, {
            filters: none,
            accounts,
        });
        expect(
            screen.queryByRole('button', { name: 'Remove the filters' }),
        ).not.toBeInTheDocument();
        empty.unmount();

        const view = renderPage(FmMovementFilters, {
            filters: {
                search: 'spesa',
                kind: 'expense',
                account: 1,
                min: 20,
                max: 100,
                from: '2026-09-01',
                to: '2026-10-31',
            },
            accounts,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove the filters' }),
        );

        expect(view.emitted('change')?.at(-1)).toEqual([none]);
    });

    it('empties the amounts and the days when the page takes the filters away', async () => {
        const view = renderPage(FmMovementFilters, {
            filters: { ...none, min: 20, from: '2026-09-01' },
            accounts,
        });
        await settle();
        expect(screen.getByLabelText('From')).toHaveValue('01/09/2026');
        expect(screen.getByLabelText('At least')).not.toHaveValue('');

        await view.rerender({ filters: none, accounts });
        await settle();

        // an empty datepicker shows its mask in jsdom
        expect(screen.getByLabelText('From')).not.toHaveValue('01/09/2026');
        expect(screen.getByLabelText('At least')).toHaveValue('');
        expect(view.emitted('change')).toBeUndefined();
    });

    it('shows the problem the server found on a field', () => {
        renderPage(FmMovementFilters, {
            filters: { ...none, min: 50, max: 10 },
            accounts,
            errors: {
                max: 'The max field must be greater than or equal to 50.',
            },
        });

        expect(
            screen.getByText(
                'The max field must be greater than or equal to 50.',
            ),
        ).toBeInTheDocument();
    });
});
