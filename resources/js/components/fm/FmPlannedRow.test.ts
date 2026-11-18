import { render, screen } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { danceRow, gasRow, netflixRow, salaryRow } from '@/test/fixtures';
import FmPlannedRow from './FmPlannedRow.vue';

describe('FmPlannedRow', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
    });
    afterEach(() => vi.useRealTimers());

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

    it('writes the commission that the amount includes', () => {
        render(FmPlannedRow, {
            props: { row: { ...danceRow, amount: 46, fee: 1 }, href: '/x' },
        });

        expect(screen.getByText('46,00 €')).toBeInTheDocument();
        expect(
            screen.getByText('of which 1,00 € commission'),
        ).toBeInTheDocument();
    });

    it('marks an estimated amount with a tilde', () => {
        render(FmPlannedRow, { props: { row: gasRow, href: '/x' } });

        expect(screen.getByText('~55,00 €')).toBeInTheDocument();
    });

    it('says the whole family when nobody is named', () => {
        render(FmPlannedRow, {
            props: { row: { ...gasRow, person: null }, href: '/x' },
        });

        expect(screen.getByText('Whole family')).toBeInTheDocument();
    });

    it('writes only how it repeats when there is no card, and when it starts later', () => {
        render(FmPlannedRow, { props: { row: netflixRow, href: '/x' } });

        expect(screen.getByRole('link')).toHaveTextContent(
            'every month, on day 4',
        );
        expect(screen.getByRole('link')).toHaveTextContent('Next on 04/11');
    });

    it('writes the year of a next due date in another year', () => {
        render(FmPlannedRow, {
            props: {
                row: { ...danceRow, next_date: '2027-11-15' },
                href: '/x',
            },
        });

        expect(screen.getByRole('link')).toHaveTextContent(
            'Next on 15/11/2027',
        );
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
