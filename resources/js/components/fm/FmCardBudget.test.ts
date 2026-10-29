import { screen, within } from '@testing-library/vue';
import {
    describe,
    expect,
    it,
    vi,
    afterEach,
    beforeEach,
} from 'vite-plus/test';
import { h } from 'vue';
import { luceBudget, policyBudget, salaryDeadline } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import FmCardBudget from './FmCardBudget.vue';

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
});

afterEach(() => {
    vi.useRealTimers();
});

/** The payments of the job of Marco, with the salary of October coming in. */
const budgetSalary: App.Data.CardBudgetData = {
    ...luceBudget,
    kind: 'income',
    next: salaryDeadline,
    next_label: 'Arriving',
    due_label: null,
    due: [],
};

describe('FmCardBudget', () => {
    it('shows the figures, the bills to pay, the data in between, the planned expenses and the bills paid, in this order', () => {
        renderPage(FmCardBudget, {
            budget: luceBudget,
            card: { type: 'utility', id: 3 },
        });

        expect(screen.getByText('Planned')).toBeInTheDocument();
        expect(screen.getByText('per month, estimated')).toBeInTheDocument();
        expect(screen.getByText('9 bills')).toBeInTheDocument();

        const due = screen.getByRole('region', { name: 'To pay' });
        const late = within(due).getByRole('link', {
            name: /Bolletta luce di settembre/,
        });
        expect(late).toHaveAttribute(
            'href',
            '/budget/planned/expense/7/occurrences/2026-10-23',
        );
        expect(within(late).getByText('~71,30 €')).toBeInTheDocument();

        const titles = screen
            .getAllByRole('heading', { level: 2 })
            .map((heading) => heading.textContent);
        expect(titles).toEqual(['To pay', 'Expected expenses', 'Bills paid']);
    });

    it('puts the renewal in the box at the top, which opens the due date', () => {
        renderPage(FmCardBudget, {
            budget: policyBudget,
            card: { type: 'insurance_policy', id: 1 },
        });

        const box = screen.getByRole('link', { name: /Renewal/ });
        expect(box).toHaveAttribute(
            'href',
            '/budget/planned/expense/12/occurrences/2026-11-21',
        );
        expect(within(box).getByText('Assicurazione auto')).toBeInTheDocument();
        expect(within(box).getByText('418,00 €')).toBeInTheDocument();
        expect(
            within(box).getByText('21/11/2026 · in 26 days · Conto BPER'),
        ).toBeInTheDocument();
        expect(screen.getByText('Premium')).toBeInTheDocument();
    });

    it('names the instalment of a loan and shows how far it is paid', () => {
        renderPage(FmCardBudget, {
            budget: {
                ...policyBudget,
                next_label: 'Next instalment',
                installment_number: 33,
                installments_count: 48,
                installments_paid: 32,
                paid_amount: 6048,
                left_amount: 3024,
            },
            card: { type: 'loan', id: 2 },
        });

        expect(screen.getByText('Instalment 33 of 48')).toBeInTheDocument();
        expect(screen.getByText('32 of 48')).toBeInTheDocument();
        expect(screen.getByRole('progressbar')).toHaveAttribute(
            'aria-valuenow',
            '32',
        );
        expect(screen.getByText('Paid 6.048,00 €')).toBeInTheDocument();
        expect(screen.getByText('Left 3.024,00 €')).toBeInTheDocument();
    });

    it('places the tiles of the card beside the figures and its data before the planned expenses', () => {
        renderPage({
            render: () =>
                h(
                    FmCardBudget,
                    { budget: luceBudget, card: { type: 'utility', id: 3 } },
                    {
                        tiles: () => h('div', 'Expiry tile'),
                        default: () => h('h2', 'Data'),
                    },
                ),
        });

        expect(screen.getByText('Expiry tile')).toBeInTheDocument();
        const titles = screen
            .getAllByRole('heading', { level: 2 })
            .map((heading) => heading.textContent);
        expect(titles).toEqual([
            'To pay',
            'Data',
            'Expected expenses',
            'Bills paid',
        ]);
    });

    it('without payments, keeps only the tiles and the data', () => {
        renderPage({
            render: () =>
                h(
                    FmCardBudget,
                    { budget: null, card: { type: 'belonging', id: 2 } },
                    {
                        tiles: () => h('div', 'Warranty tile'),
                        default: () => h('h2', 'Data'),
                    },
                ),
        });

        expect(screen.getByText('Warranty tile')).toBeInTheDocument();
        expect(
            screen
                .getAllByRole('heading', { level: 2 })
                .map((h2) => h2.textContent),
        ).toEqual(['Data']);
    });

    it('opens It arrived on the salary coming in, for whoever can record it', () => {
        const salary = {
            ...budgetSalary,
        };
        const first = renderPage(FmCardBudget, {
            budget: salary,
            card: { type: 'employment', id: 3 },
        });

        expect(screen.getByRole('link', { name: /Arriving/ })).toHaveAttribute(
            'href',
            '/budget/planned/income/2/occurrences/2026-10-27?pay=1',
        );
        first.unmount();

        renderPage(FmCardBudget, {
            budget: { ...salary, can_pay: false },
            card: { type: 'employment', id: 3 },
        });
        expect(screen.getByRole('link', { name: /Arriving/ })).toHaveAttribute(
            'href',
            '/budget/planned/income/2/occurrences/2026-10-27',
        );
    });
});
