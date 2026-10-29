import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { luceBudget, luceRow } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import CardPayments from './CardPayments.vue';

const props = {
    card: luceRow,
    budget: luceBudget,
    numbers: [
        {
            label: 'Paid in 2026',
            amount: 642.1,
            count: null,
            hint: '9 bills',
            hint_amount: null,
        },
        {
            label: 'On average',
            amount: 71.34,
            count: null,
            hint: 'per bill, :amount planned',
            hint_amount: 72,
        },
    ],
    counts: { 2026: '3 bills', 2025: '1 bill' },
};

describe('Payments of a card', () => {
    it('shows the bills paid of the card year by year, with the figures', async () => {
        renderPage(CardPayments, props);

        expect(
            screen.getByRole('heading', { name: 'Bills paid', level: 1 }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Luce - Edison/ }),
        ).toHaveAttribute('href', '/cards/utility/3');
        expect(
            screen.getByText('per bill, 72,00 € planned'),
        ).toBeInTheDocument();
        expect(screen.getByText('3 bills')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Bolletta luce di giugno/ }),
        ).toHaveAttribute('href', '/budget/movements/expense/39');

        await userEvent.click(screen.getByRole('button', { name: /2025/ }));
        expect(
            screen.getByText('Bolletta luce di novembre'),
        ).toBeInTheDocument();
    });

    it('records a payment and says which payments it lists', () => {
        renderPage(CardPayments, props);

        expect(
            screen.getByRole('link', { name: 'Record payment' }),
        ).toHaveAttribute(
            'href',
            '/budget/planned/expense/7/occurrences/2026-10-23?pay=1',
        );
        expect(
            screen.getByText(/These are the payments linked to Luce\./),
        ).toBeInTheDocument();
    });

    it('says when nothing is paid yet', () => {
        renderPage(CardPayments, {
            ...props,
            budget: { ...luceBudget, payments: [] },
            numbers: [],
            counts: {},
        });

        const list = screen.getByTestId('payments');
        expect(
            within(list).getByText('No payments recorded.'),
        ).toBeInTheDocument();
    });
});
