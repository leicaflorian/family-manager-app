import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmMonthSwitch from './FmMonthSwitch.vue';

describe('FmMonthSwitch', () => {
    it('writes the month and goes to the previous and next ones', async () => {
        const view = render(FmMonthSwitch, { props: { month: '2026-10' } });

        expect(screen.getByText('October 2026')).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Previous month' }),
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Next month' }),
        );

        expect(view.emitted('change')).toEqual([['2026-09'], ['2026-11']]);
    });

    it('goes over the end of the year', async () => {
        const view = render(FmMonthSwitch, { props: { month: '2026-12' } });

        await userEvent.click(
            screen.getByRole('button', { name: 'Next month' }),
        );

        expect(view.emitted('change')).toEqual([['2027-01']]);
    });

    it('writes a span of months and moves by the whole span', async () => {
        const view = render(FmMonthSwitch, {
            props: { month: '2026-09', span: 12 },
        });

        expect(
            screen.getByText('October 2025 – September 2026'),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'A year earlier' }),
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'A year later' }),
        );

        expect(view.emitted('change')).toEqual([['2025-09'], ['2027-09']]);
    });

    it('turns off the arrow forward when the last month of the span is max', () => {
        render(FmMonthSwitch, {
            props: { month: '2026-11', span: 12, max: '2026-11' },
        });

        expect(
            screen.getByRole('button', { name: 'A year later' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'A year earlier' }),
        ).toBeEnabled();
    });

    it('stops at the first and last months instead of jumping past them', async () => {
        const view = render(FmMonthSwitch, {
            props: {
                month: '2026-06',
                span: 12,
                min: '2025-11',
                max: '2026-11',
            },
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'A year earlier' }),
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'A year later' }),
        );

        expect(view.emitted('change')).toEqual([['2025-11'], ['2026-11']]);
    });

    it('bounds the last month of the span with min, not the first', () => {
        render(FmMonthSwitch, {
            props: { month: '2026-09', span: 12, min: '2026-09' },
        });

        // October 2025 is before min, yet the span may show it: min bounds September 2026
        expect(
            screen.getByText('October 2025 – September 2026'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'A year earlier' }),
        ).toBeDisabled();
    });

    it('turns off the arrow back when the month (the last of the span) is min', () => {
        render(FmMonthSwitch, {
            props: { month: '2026-03', min: '2026-03' },
        });

        expect(
            screen.getByRole('button', { name: 'Previous month' }),
        ).toBeDisabled();
    });

    it('goes back to the last month with Today', async () => {
        const view = render(FmMonthSwitch, {
            props: { month: '2025-11', span: 12, max: '2026-11', today: true },
        });

        await userEvent.click(screen.getByRole('button', { name: 'Today' }));

        expect(view.emitted('change')).toEqual([['2026-11']]);
    });

    it('shows Today only away from the last month', () => {
        render(FmMonthSwitch, {
            props: { month: '2026-11', span: 12, max: '2026-11', today: true },
        });

        expect(
            screen.queryByRole('button', { name: 'Today' }),
        ).not.toBeInTheDocument();
    });
});
