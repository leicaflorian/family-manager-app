import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { formatEuro, formatNumber, formatQuantity } from '@/lib/format';
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

    it('writes euros above the chosen bar in the spending view', () => {
        const { container } = render(FmBarChart, {
            props: {
                months: [{ month: '2026-01', value: 86.1, mark: 79.4 }],
                marks: 'previous',
                format: formatEuro,
                selected: '2026-01',
            },
        });

        expect(container.querySelector('svg')).toHaveTextContent('86,10 €');
    });

    it('writes the bare quantity above the chosen bar with short', () => {
        const { container } = render(FmBarChart, {
            props: {
                months: kwh,
                marks: 'previous',
                format: inKwh,
                short: formatNumber,
                selected: '2026-01',
            },
        });

        const texts = [...container.querySelectorAll('svg text')].map((text) =>
            text.textContent?.trim(),
        );
        expect(texts).toContain('290');
        expect(texts).not.toContain('290 kWh');
    });

    it('writes above the chosen bar with short when given', () => {
        const { container } = render(FmBarChart, {
            props: {
                months: kwh,
                marks: 'previous',
                format: inKwh,
                short: (value: number) => `~${value}`,
                selected: '2026-01',
            },
        });

        expect(container.querySelector('svg')).toHaveTextContent('~290');
    });
});

describe('FmBarChart, tooltips', () => {
    it('shows the text of a month in a bubble on hover, with no native title', async () => {
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
        expect(january).not.toHaveAttribute('title');

        await userEvent.hover(january);

        expect(await screen.findByRole('tooltip')).toHaveTextContent(
            'January 2026: 290 kWh, 86,10 €; January 2025: 248 kWh',
        );
    });

    it('shows no bubble for a month without data', async () => {
        const { container } = render(FmBarChart, {
            props: {
                months: kwh,
                marks: 'previous',
                format: inKwh,
                selected: null,
            },
        });

        expect(
            container.querySelectorAll('[role="group"] button'),
        ).toHaveLength(5);
        await userEvent.hover(
            container.querySelector('[role="group"] > span')!,
        );

        expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();
    });

    it('shows the bubble on hover over a bar of the image too, without a native title', async () => {
        const { container } = render(FmBarChart, {
            props: { months: kwh, marks: 'previous', format: inKwh },
        });
        expect(container.querySelector('svg title')).toBeNull();

        await userEvent.hover(
            container.querySelectorAll('[data-bar-target]')[0],
        );

        expect(await screen.findByRole('tooltip')).toHaveTextContent(
            'November 2025: 238 kWh, 74,50 €; November 2024: 252 kWh',
        );
    });
});
