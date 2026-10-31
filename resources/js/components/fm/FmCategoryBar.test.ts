import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmCategoryBar from './FmCategoryBar.vue';

describe('FmCategoryBar', () => {
    it('shows the category, what was spent on what was planned and the comment', () => {
        render(FmCategoryBar, {
            props: {
                category: 'Bollette e utenze',
                spent: 29.9,
                planned: 223.3,
                lateTitle: 'luce',
            },
        });

        expect(screen.getByText('Bollette e utenze')).toBeInTheDocument();
        expect(screen.getByText('29,90 €')).toBeInTheDocument();
        expect(screen.getByText('of 223,30 €')).toBeInTheDocument();
        expect(
            screen.getByText('193,40 € less · luce late'),
        ).toBeInTheDocument();
    });

    it('says what is not planned and has no mark for it', () => {
        const { container } = render(FmCategoryBar, {
            props: { category: 'Salute', spent: 108.4, planned: 0 },
        });

        expect(
            screen.getByText('108,40 € more · not planned'),
        ).toBeInTheDocument();
        expect(container.querySelector('[data-planned]')).toBeNull();
    });

    it('describes the bar in its title', () => {
        const { container } = render(FmCategoryBar, {
            props: { category: 'Casa', spent: 612, planned: 612 },
        });

        expect(container.querySelector('[title]')).toHaveAttribute(
            'title',
            'Casa: spent 612,00 €, planned 612,00 €',
        );
    });

    it('paints in red what goes over the plan, from the mark on', () => {
        const { container } = render(FmCategoryBar, {
            props: { category: 'Auto', spent: 120, planned: 80 },
        });

        const over = container.querySelector('[data-over]');
        expect(over).toHaveClass('bg-overdue');
        expect(over).toHaveStyle({ left: '66.66666666666666%' });
        expect(screen.getByText('120,00 €')).toHaveClass('text-overdue');
    });

    it('paints the whole bar in red when nothing was planned', () => {
        const { container } = render(FmCategoryBar, {
            props: { category: 'Salute', spent: 108.4, planned: 0 },
        });

        expect(container.querySelector('[data-over]')).toHaveStyle({
            left: '0%',
        });
        expect(screen.getByText('108,40 €')).toHaveClass('text-overdue');
    });

    it('has nothing in red within the plan', () => {
        const { container } = render(FmCategoryBar, {
            props: { category: 'Casa', spent: 612, planned: 612 },
        });

        expect(container.querySelector('[data-over]')).toBeNull();
        expect(screen.getByText('612,00 €')).not.toHaveClass('text-overdue');
    });
});
