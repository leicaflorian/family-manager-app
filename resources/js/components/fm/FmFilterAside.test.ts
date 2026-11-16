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

        expect(
            screen.getByRole('complementary', { name: 'Detail' }),
        ).toHaveTextContent('The detail');
        expect(screen.queryByText('The filters')).not.toBeInTheDocument();
    });

    it('puts the detail-actions slot in the header, before the ×', () => {
        render(FmFilterAside, {
            props: { detail: true },
            slots: { ...slots, 'detail-actions': '<button>Extra</button>' },
        });

        const names = screen
            .getAllByRole('button')
            .map((b) => b.getAttribute('aria-label') ?? b.textContent);
        expect(names.indexOf('Extra')).toBe(names.indexOf('Close') - 1);
    });

    it('emits close from Back to the filters and from Close', async () => {
        const view = render(FmFilterAside, {
            props: { detail: true },
            slots,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Back to the filters' }),
        );
        expect(view.emitted().close).toHaveLength(1);

        await userEvent.click(screen.getByRole('button', { name: 'Close' }));
        expect(view.emitted().close).toHaveLength(2);
    });

    it('keeps the column within the window below the top bar, scrolling inside it', () => {
        for (const detail of [true, false]) {
            const view = render(FmFilterAside, { props: { detail }, slots });

            expect(screen.getByRole('complementary')).toHaveClass(
                'sticky',
                'top-(--app-header)',
                'max-h-[calc(100dvh-var(--app-header,0px)-(--spacing(6)))]',
                'overflow-y-auto',
            );
            view.unmount();
        }
    });
});
