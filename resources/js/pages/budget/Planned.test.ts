import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { danceRow, gasRow, netflixRow, salaryRow } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Planned from './Planned.vue';

const props = {
    expenses: [gasRow, netflixRow, danceRow],
    incomes: [salaryRow],
    ended: [],
    monthly_out: 1482.6,
    monthly_in: 3570,
    canEdit: true,
};

describe('Planned expenses', () => {
    it('shows the monthly averages at the top', () => {
        renderPage(Planned, props);

        expect(
            screen.getByRole('heading', { name: 'Planned expenses', level: 1 }),
        ).toBeInTheDocument();
        expect(screen.getByText('Going out, on average')).toBeInTheDocument();
        expect(screen.getByText('1.482,60 €')).toBeInTheDocument();
        expect(
            screen.getByText('per month, 3 planned expenses'),
        ).toBeInTheDocument();
        expect(screen.getByText('Coming in')).toBeInTheDocument();
        expect(screen.getByText('3.570,00 €')).toBeInTheDocument();
        expect(
            screen.getByText('per month, 1 planned income'),
        ).toBeInTheDocument();
    });

    it('lists the two sections with their number and the row of Netflix', () => {
        renderPage(Planned, props);

        expect(
            screen.getByRole('heading', { name: 'Going out · 3' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Coming in · 1' }),
        ).toBeInTheDocument();

        const netflix = screen.getByRole('link', { name: /Netflix/ });
        expect(netflix).toHaveAttribute('href', '/budget/planned/expense/11');
        expect(netflix).toHaveTextContent('every month, on day 4');
        expect(netflix).toHaveTextContent('Laura');
        expect(netflix).toHaveTextContent('Next on 04/11');
        expect(netflix).toHaveTextContent('13,99 €');
        expect(
            screen.getByRole('link', { name: /Stipendio di Marco/ }),
        ).toHaveAttribute('href', '/budget/planned/income/2');
    });

    it('opens the new planned expense and the new planned income', () => {
        renderPage(Planned, props);

        expect(
            screen.getByRole('link', { name: 'New planned expense' }),
        ).toHaveAttribute('href', '/budget/planned/expense/create');
        expect(
            screen.getByRole('link', { name: 'New planned income' }),
        ).toHaveAttribute('href', '/budget/planned/income/create');
    });

    it('has no way to add for a member', () => {
        renderPage(Planned, { ...props, canEdit: false });

        expect(
            screen.queryByRole('link', { name: 'New planned expense' }),
        ).not.toBeInTheDocument();
    });

    it('keeps the ended ones folded', async () => {
        renderPage(Planned, {
            ...props,
            ended: [
                {
                    ...danceRow,
                    id: 8,
                    title: 'Retta nido',
                    ended: true,
                    next_date: null,
                    end_date: '2026-06-30',
                },
            ],
        });

        expect(screen.queryByText('Retta nido')).not.toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Ended (1)' }),
        );

        const link = screen.getByRole('link', { name: /Retta nido/ });
        expect(
            within(link).getByText(/until 30\/06\/2026/),
        ).toBeInTheDocument();
    });

    it('invites to add the first planned expense when there is none', () => {
        renderPage(Planned, {
            ...props,
            expenses: [],
            incomes: [],
            monthly_out: 0,
            monthly_in: 0,
        });

        expect(screen.getByText('No planned expenses yet')).toBeInTheDocument();
        expect(
            screen.queryByRole('heading', { name: /Going out/ }),
        ).not.toBeInTheDocument();
    });
});
