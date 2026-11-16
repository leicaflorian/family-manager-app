import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmFilterAside from './FmFilterAside.vue';

const slots = {
    filters: '<p>The filters</p>',
    detail: '<p>The detail</p>',
};

describe('FmFilterAside', () => {
    it('shows the filters in a region named Filters', () => {
        render(FmFilterAside, { props: { detail: false }, slots });

        const region = screen.getByRole('complementary', { name: 'Filters' });
        expect(region).toHaveTextContent('The filters');
        expect(screen.queryByText('The detail')).not.toBeInTheDocument();
    });

    it('shows the detail and not the filters when detail is on', () => {
        render(FmFilterAside, { props: { detail: true }, slots });

        expect(screen.getByText('The detail')).toBeInTheDocument();
        expect(screen.queryByText('The filters')).not.toBeInTheDocument();
    });

    it('emits close from Back to the filters and from Close', async () => {
        const { emitted } = render(FmFilterAside, {
            props: { detail: true },
            slots,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Back to the filters' }),
        );
        expect(emitted().close).toHaveLength(1);

        await userEvent.click(screen.getByRole('button', { name: 'Close' }));
        expect(emitted().close).toHaveLength(2);
    });
});
