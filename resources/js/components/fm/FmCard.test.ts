import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmCard from './FmCard.vue';

describe('FmCard', () => {
    it('shows the title as a heading and the content', () => {
        render(FmCard, {
            props: { title: 'People' },
            slots: { default: '<p>Marco</p>' },
        });

        expect(
            screen.getByRole('heading', { name: 'People' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Marco')).toBeInTheDocument();
    });

    it('has no padding inside as a list, because the rows have their own', () => {
        const result = render(FmCard, {
            props: { list: true },
            slots: { default: '<p>Row</p>' },
        });

        expect(result.container.querySelector('[data-card]')).not.toHaveClass(
            'p-5',
        );
    });
});
