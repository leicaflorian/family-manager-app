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
});
