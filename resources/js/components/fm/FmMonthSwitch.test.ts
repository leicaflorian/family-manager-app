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
});
