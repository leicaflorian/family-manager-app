import { screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { luceBudget, salaryRow } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import FmCardPayments from './FmCardPayments.vue';

const card = { type: 'utility', id: 3 };

describe('FmCardPayments', () => {
    it('lists the planned expenses, with Add, and the latest payments, with Record payment and the link to all of them', () => {
        renderPage(FmCardPayments, { budget: luceBudget, card });

        const planned = screen.getByRole('region', {
            name: 'Expected expenses',
        });
        expect(
            within(planned).getByRole('link', { name: 'Add' }),
        ).toHaveAttribute(
            'href',
            '/budget/planned/expense/create?card=utility%3A3',
        );
        expect(
            within(planned).getByRole('link', { name: /Luce/ }),
        ).toHaveAttribute('href', '/budget/planned/expense/7');

        const paid = screen.getByRole('region', { name: 'Bills paid' });
        expect(within(paid).getByText('642,10 €')).toBeInTheDocument();
        expect(
            within(paid).getByRole('link', {
                name: /Bolletta luce di luglio/,
            }),
        ).toHaveAttribute(
            'href',
            expect.stringMatching(/^\/budget\/movements\/expense\/\d+$/),
        );
        expect(
            within(paid).getByText('Bolletta luce di novembre'),
        ).toBeInTheDocument();
        expect(
            within(paid).queryByRole('navigation', { name: 'Pages' }),
        ).not.toBeInTheDocument();
        expect(
            within(paid).getByRole('link', { name: 'Record payment' }),
        ).toHaveAttribute(
            'href',
            '/budget/planned/expense/7/occurrences/2026-10-23?pay=1',
        );
        expect(
            screen.getByRole('link', {
                name: 'See all the bills, year by year',
            }),
        ).toHaveAttribute('href', '/cards/utility/3/payments');
    });

    it('shows the payments a page at a time, with the pager of the documents', () => {
        renderPage(FmCardPayments, {
            budget: {
                ...luceBudget,
                payments_previous: '/cards/utility/3',
                payments_next: '/cards/utility/3?payments_page=3',
            },
            card,
        });

        const pages = screen.getByRole('navigation', { name: 'Pages' });
        expect(
            within(pages).getByRole('link', { name: 'Previous' }),
        ).toHaveAttribute('href', '/cards/utility/3');
        expect(
            within(pages).getByRole('link', { name: 'Next' }),
        ).toHaveAttribute('href', '/cards/utility/3?payments_page=3');
    });

    it('asks to create the planned expense first', () => {
        renderPage(FmCardPayments, {
            budget: {
                ...luceBudget,
                planned: [],
                payments: [],
                pay: [],
                can_pay: false,
            },
            card,
        });

        expect(
            screen.getByText('Create the planned expense first.'),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Record payment' }),
        ).not.toBeInTheDocument();
    });

    it('says when nothing is paid yet, and leaves Add to an admin', () => {
        renderPage(FmCardPayments, {
            budget: { ...luceBudget, payments: [], can_add_planned: false },
            card,
        });

        expect(screen.getByText('No payments recorded.')).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Add' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: /See all/ }),
        ).not.toBeInTheDocument();
    });

    it('records an income of an employment', () => {
        renderPage(FmCardPayments, {
            budget: {
                ...luceBudget,
                kind: 'income',
                planned_label: 'Expected income',
                planned: [salaryRow],
                payments_label: 'Salaries received',
            },
            card: { type: 'employment', id: 3 },
        });

        expect(screen.getByRole('link', { name: 'Add' })).toHaveAttribute(
            'href',
            '/budget/planned/income/create?card=employment%3A3',
        );
        expect(
            screen.getByRole('link', { name: 'Record income' }),
        ).toBeInTheDocument();
    });

    it('has no sections the card does not have', () => {
        renderPage(FmCardPayments, {
            budget: {
                ...luceBudget,
                planned_label: null,
                payments_label: null,
            },
            card,
        });

        expect(screen.queryByRole('region')).not.toBeInTheDocument();
    });

    it('asks a job without a planned income to create it first', () => {
        renderPage(FmCardPayments, {
            budget: {
                ...luceBudget,
                kind: 'income',
                planned_label: 'Expected income',
                planned: [],
                payments_label: 'Salaries received',
                payments: [],
                pay: [],
                can_pay: false,
            },
            card: { type: 'employment', id: 3 },
        });

        expect(
            screen.getByText('Create the planned income first.'),
        ).toBeInTheDocument();
    });

    it('does not ask a loan to create its planned expense, which is automatic', () => {
        renderPage(FmCardPayments, {
            budget: {
                ...luceBudget,
                planned_label: null,
                planned: [],
                payments_label: 'Instalments paid',
                payments: [],
                pay: [],
                can_pay: false,
            },
            card: { type: 'loan', id: 2 },
        });

        expect(
            screen.queryByText('Create the planned expense first.'),
        ).not.toBeInTheDocument();
        expect(screen.getByText('No payments recorded.')).toBeInTheDocument();
    });

    it('shows only the expenses of a card without planned expenses', () => {
        renderPage(FmCardPayments, {
            budget: {
                ...luceBudget,
                numbers: [],
                due_label: null,
                due: [],
                planned_label: null,
                planned: [],
                can_add_planned: false,
                payments_label: 'Expenses',
                payments_more: 'See all the expenses, year by year',
                pay: [],
                can_pay: false,
            },
            card: { type: 'health_event', id: 4 },
        });

        expect(
            screen
                .getAllByRole('heading', { level: 2 })
                .map((h2) => h2.textContent),
        ).toEqual(['Expenses']);
        expect(
            screen.queryByRole('button', { name: /Record payment/ }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByText('Create the planned expense first.'),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('link', {
                name: 'See all the expenses, year by year',
            }),
        ).toHaveAttribute('href', '/cards/health_event/4/payments');
    });

    it('adds an expense to a card without planned expenses', () => {
        renderPage(FmCardPayments, {
            budget: {
                ...luceBudget,
                planned_label: null,
                planned: [],
                can_add_planned: false,
                payments_label: 'Expenses',
                payments: [],
                pay: [],
                can_pay: false,
                can_add_expense: true,
            },
            card: { type: 'belonging', id: 5 },
        });

        const expenses = screen.getByRole('region', { name: 'Expenses' });
        const add = within(expenses).getByRole('link', { name: 'Add expense' });
        expect(add).toHaveAttribute(
            'href',
            '/budget/movements/create?card=belonging%3A5',
        );
        expect(add).toHaveClass('font-normal', 'hover:underline');
        expect(
            within(expenses).getByText('No payments recorded.'),
        ).toBeInTheDocument();
    });

    it('leaves out Add expense when it is not offered', () => {
        renderPage(FmCardPayments, { budget: luceBudget, card });

        expect(
            screen.queryByRole('link', { name: 'Add expense' }),
        ).not.toBeInTheDocument();
    });
});
