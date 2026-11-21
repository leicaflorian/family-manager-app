import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { formatQuantity } from '@/lib/format';
import type { ChartMonth } from '@/lib/charts';
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
        expect(
            screen.getByRole('button', { name: 'See as a table' }),
        ).not.toHaveAttribute('aria-pressed');

        await userEvent.click(
            screen.getByRole('button', { name: 'See as a table' }),
        );

        expect(
            screen.getByRole('button', { name: 'See as a chart' }),
        ).not.toHaveAttribute('aria-pressed');
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

    it('draws the bars of *Planned and spent* as an image, not as buttons', () => {
        render(FmBarChart, { props: { months: monthBars } });

        expect(
            screen.queryByRole('button', { pressed: false }),
        ).not.toBeInTheDocument();
    });
});

/** A year of electricity, from October 2025 to September 2026. */
const kwh: ChartMonth[] = [
    { month: '2025-10', value: null, mark: null },
    { month: '2025-11', value: 238, mark: 252, amount: 74.5 },
    { month: '2025-12', value: 276, mark: null, amount: 82.3 },
    { month: '2026-01', value: 290, mark: 248, amount: 86.1 },
    { month: '2026-02', value: 214, mark: 230, estimated: true },
    {
        month: '2026-03',
        value: 120,
        mark: 246,
        partial: true,
        note: 'until the 14th',
    },
];

/** Writes a quantity in kWh. */
const inKwh = (value: number): string => formatQuantity(value, 'kwh');

describe('FmBarChart, consumption', () => {
    it('says the mark is the same month of the year before', () => {
        render(FmBarChart, {
            props: {
                months: kwh,
                marks: 'previous',
                format: inKwh,
                title: 'kWh a month',
                valueLabel: 'kWh',
            },
        });

        expect(screen.getByText('kWh a month')).toBeInTheDocument();
        expect(screen.getByText('Same month last year')).toBeInTheDocument();
        expect(screen.queryByText('Planned')).not.toBeInTheDocument();
        expect(screen.getByText('Partial month')).toBeInTheDocument();
        expect(screen.getByText('Estimate')).toBeInTheDocument();
    });

    it('makes each month with a bar a button that says month, quantity, euros and last year', () => {
        render(FmBarChart, {
            props: {
                months: kwh,
                marks: 'previous',
                format: inKwh,
                selected: '2026-01',
            },
        });

        const january = screen.getByRole('button', {
            name: 'January 2026: 290 kWh, 86,10 €; January 2025: 248 kWh',
        });
        expect(january).toHaveAttribute('aria-pressed', 'true');
        expect(
            screen.getByRole('button', { name: /^November 2025: 238 kWh/ }),
        ).toHaveAttribute('aria-pressed', 'false');
        expect(
            screen.getByRole('button', {
                name: 'December 2025: 276 kWh, 82,30 €',
            }),
        ).toBeInTheDocument();
    });

    it('has no bar for a month without data, not a bar at zero', () => {
        render(FmBarChart, {
            props: { months: kwh, marks: 'previous', selected: null },
        });

        expect(
            screen.queryByRole('button', { name: /October 2025/ }),
        ).not.toBeInTheDocument();
        expect(screen.getAllByRole('button', { pressed: false })).toHaveLength(
            5,
        );
    });

    it('tells partial and estimated months by their look and in words', () => {
        const { container } = render(FmBarChart, {
            props: {
                months: kwh,
                marks: 'previous',
                format: inKwh,
                selected: null,
            },
        });

        expect(
            screen.getByRole('button', { name: /^February 2026.*estimated$/ }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', {
                name: /^March 2026.*until the 14th, partial month$/,
            }),
        ).toBeInTheDocument();
        expect(container.querySelector('[data-bar="partial"]')).not.toBeNull();
        expect(
            container.querySelector('[data-bar="estimated"]'),
        ).not.toBeNull();
    });

    it('asks for the month of the bar the person chooses', async () => {
        const view = render(FmBarChart, {
            props: { months: kwh, marks: 'previous', selected: null },
        });

        await userEvent.click(
            screen.getByRole('button', { name: /^December 2025/ }),
        );

        expect(view.emitted('select')).toEqual([['2025-12']]);
    });

    it('goes to the month next to the chosen one with the arrows', async () => {
        const view = render(FmBarChart, {
            props: { months: kwh, marks: 'previous', selected: '2026-01' },
        });

        screen.getByRole('button', { pressed: true }).focus();
        await userEvent.keyboard('{ArrowRight}');
        await userEvent.keyboard('{ArrowLeft}');

        // The focus follows the arrow, so the second press starts from February
        expect(view.emitted('select')).toEqual([['2026-02'], ['2026-01']]);
        expect(
            screen.getByRole('button', { name: /^January 2026/ }),
        ).toHaveFocus();
    });

    it('does not go past the first month with data', async () => {
        const view = render(FmBarChart, {
            props: { months: kwh, marks: 'previous', selected: '2025-11' },
        });

        screen.getByRole('button', { pressed: true }).focus();
        await userEvent.keyboard('{ArrowLeft}');

        expect(view.emitted('select')).toBeUndefined();
    });

    it('shows the units in the table', async () => {
        render(FmBarChart, {
            props: {
                months: kwh,
                marks: 'previous',
                format: inKwh,
                valueLabel: 'kWh',
            },
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'See as a table' }),
        );

        const table = screen.getByRole('table');
        expect(
            within(table).getByRole('columnheader', {
                name: 'Same month last year',
            }),
        ).toBeInTheDocument();
        expect(within(table).getByText('290 kWh')).toBeInTheDocument();
        expect(within(table).getByText('248 kWh')).toBeInTheDocument();
        expect(within(table).getByText(/until the 14th/)).toBeInTheDocument();
    });
});
