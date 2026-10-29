import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { monthBars } from '@/test/fixtures';
import FmBarChart from './FmBarChart.vue';

describe('FmBarChart', () => {
    it('is an image that names the six months', () => {
        render(FmBarChart, { props: { months: monthBars } });

        const chart = screen.getByRole('img');
        expect(chart).toHaveAccessibleName(
            'Spent and planned from May to October 2026',
        );
    });

    it('marks the month in progress', () => {
        render(FmBarChart, { props: { months: monthBars } });

        expect(screen.getByText('Oct (in progress)')).toBeInTheDocument();
        expect(screen.getByText('May')).toBeInTheDocument();
    });

    it('shows the same numbers as a table', async () => {
        render(FmBarChart, { props: { months: monthBars } });

        expect(screen.queryByRole('table')).not.toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'See as a table' }),
        );

        const table = screen.getByRole('table');
        expect(within(table).getAllByRole('row')).toHaveLength(7);
        expect(within(table).getByText('1.284,20 €')).toBeInTheDocument();
        expect(within(table).getByText('1.469,30 €')).toBeInTheDocument();
        expect(within(table).getByText('1.488,40 €')).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'See as a chart' }),
        );
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });

    it('has a legend for spent and planned', () => {
        render(FmBarChart, { props: { months: monthBars } });

        expect(screen.getByText('Spent')).toBeInTheDocument();
        expect(screen.getByText('Planned')).toBeInTheDocument();
    });
});
