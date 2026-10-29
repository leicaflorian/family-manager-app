import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { danceRow, gasRow, netflixRow, salaryRow } from '@/test/fixtures';
import FmPlannedRow from './FmPlannedRow.vue';

describe('FmPlannedRow', () => {
    it('leads to the planned item, with its card, how it repeats, who it is for, the next due date and the amount', () => {
        render(FmPlannedRow, {
            props: { row: danceRow, href: '/budget/planned/expense/4' },
        });

        const link = screen.getByRole('link');
        expect(link).toHaveAttribute('href', '/budget/planned/expense/4');
        expect(link).toHaveTextContent('Retta danza');
        expect(link).toHaveTextContent('Danza Giulia · every month, on day 5');
        expect(link).toHaveTextContent('Giulia');
        expect(link).toHaveTextContent('Next on 05/12');
        expect(link).toHaveTextContent('Carta Laura');
        expect(link).toHaveTextContent('45,00 €');
    });

    it('marks an estimated amount with a tilde', () => {
        render(FmPlannedRow, { props: { row: gasRow, href: '/x' } });

        expect(screen.getByText('~55,00 €')).toBeInTheDocument();
    });

    it('says the whole family when nobody is named', () => {
        render(FmPlannedRow, { props: { row: gasRow, href: '/x' } });

        expect(screen.getByText('Whole family')).toBeInTheDocument();
    });

    it('writes only how it repeats when there is no card, and when it starts later', () => {
        render(FmPlannedRow, { props: { row: netflixRow, href: '/x' } });

        expect(screen.getByRole('link')).toHaveTextContent(
            'every month, on day 4',
        );
        expect(screen.getByRole('link')).toHaveTextContent('Next on 04/11');
    });

    it('writes an income with a plus', () => {
        render(FmPlannedRow, { props: { row: salaryRow, href: '/x' } });

        expect(screen.getByText('+1.850,00 €')).toBeInTheDocument();
    });

    it('has no next due date once it ended', () => {
        render(FmPlannedRow, {
            props: {
                row: {
                    ...danceRow,
                    next_date: null,
                    ended: true,
                    end_date: '2026-06-30',
                },
                href: '/x',
            },
        });

        expect(screen.queryByText(/Next on/)).not.toBeInTheDocument();
        expect(screen.getByRole('link')).toHaveTextContent('until 30/06/2026');
    });
});
