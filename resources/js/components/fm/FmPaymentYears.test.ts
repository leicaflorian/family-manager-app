import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { lucePayments } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import FmPaymentYears from './FmPaymentYears.vue';

describe('FmPaymentYears', () => {
    it('lists the payments of each year with its total', () => {
        renderPage(FmPaymentYears, { years: lucePayments });

        expect(screen.getByText('2026')).toBeInTheDocument();
        expect(screen.getByText('642,10 €')).toBeInTheDocument();
        expect(screen.getByText('2025')).toBeInTheDocument();
        expect(screen.getByText('23/09')).toBeInTheDocument();
        expect(screen.getByText('Bolletta luce di agosto')).toBeInTheDocument();
        expect(screen.getAllByText('Conto BPER')).toHaveLength(4);
        expect(screen.getByText('68,90 €')).toBeInTheDocument();
    });

    it('with the counts, folds the older years and leads each payment to its page', async () => {
        renderPage(FmPaymentYears, {
            years: lucePayments,
            counts: { 2026: '3 bills', 2025: '1 bill' },
            folded: true,
            linked: true,
            fullDate: true,
        });

        expect(screen.getByText('3 bills')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Bolletta luce di agosto/ }),
        ).toHaveAttribute('href', '/budget/movements/expense/41');
        expect(screen.getByText('23/09/2026')).toBeInTheDocument();
        expect(
            screen.queryByText('Bolletta luce di novembre'),
        ).not.toBeInTheDocument();

        const older = screen.getByRole('button', { name: /2025/ });
        expect(older).toHaveAttribute('aria-expanded', 'false');
        expect(within(older).getByText('1 bill')).toBeInTheDocument();
        await userEvent.click(older);

        expect(older).toHaveAttribute('aria-expanded', 'true');
        expect(
            screen.getByText('Bolletta luce di novembre'),
        ).toBeInTheDocument();
    });

    it('leads each payment to its page keeping the short date', () => {
        renderPage(FmPaymentYears, {
            years: lucePayments,
            linked: true,
        });

        expect(
            screen.getByRole('link', { name: /Bolletta luce di agosto/ }),
        ).toHaveAttribute('href', '/budget/movements/expense/41');
        expect(screen.getByText('23/09')).toBeInTheDocument();
    });
});
