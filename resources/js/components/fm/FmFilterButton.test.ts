import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmFilterButton from './FmFilterButton.vue';

describe('FmFilterButton', () => {
    it('is named Filters and has no badge with no filter on', () => {
        render(FmFilterButton, { props: { count: 0 } });

        expect(
            screen.getByRole('button', { name: 'Filters' }),
        ).toBeInTheDocument();
        expect(screen.queryByText('0')).not.toBeInTheDocument();
    });

    it('says how many are on and shows the number', () => {
        render(FmFilterButton, { props: { count: 4 } });

        expect(
            screen.getByRole('button', { name: 'Filters, 4 on' }),
        ).toBeInTheDocument();
        expect(screen.getByText('4')).toBeVisible();
    });

    it('emits click', async () => {
        const { emitted } = render(FmFilterButton, { props: { count: 1 } });

        await userEvent.click(screen.getByRole('button'));

        expect(emitted().click).toHaveLength(1);
    });
});
